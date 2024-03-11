import os
import base64
import pika
import json
import os
import base64
from PIL import Image
from io import BytesIO
import torch
from transformers import AutoModelForImageClassification, ViTImageProcessor

processor = ViTImageProcessor.from_pretrained("Falconsai/nsfw_image_detection")
model = AutoModelForImageClassification.from_pretrained(
    "Falconsai/nsfw_image_detection"
)

RABBITMQ_HOST = "localhost"
RABBITMQ_EXCHANGE = "message_exchange"
RESULTS_QUEUE = "results_queue"


def check_image_harmful(image_path):
    try:
        img = Image.open(image_path)
        with torch.no_grad():
            inputs = processor(images=img, return_tensors="pt")
            outputs = model(**inputs)
            logits = outputs.logits
        predicted_label = logits.argmax(-1).item()

        print("Predicted label:", predicted_label)
        print(model.config.id2label[predicted_label])
        return False if model.config.id2label[predicted_label] == "neutral" else True
    except Exception as e:
        print(f"Error processing image: {e}")
        return False


def save_image_from_base64(image_base64, message_id):
    try:
        directory = "data/images"
        if not os.path.exists(directory):
            os.makedirs(directory)

        image_data = base64.b64decode(image_base64)
        image_path = f"{directory}/{message_id}.png"
        with open(image_path, "wb") as img_file:
            img_file.write(image_data)
        return image_path
    except Exception as e:
        print(f"Error saving image: {e}")
        return None


def main():
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=RABBITMQ_HOST))
    channel = connection.channel()

    channel.exchange_declare(exchange=RABBITMQ_EXCHANGE, exchange_type="topic")

    result = channel.queue_declare(queue="", exclusive=True)
    queue_name = result.method.queue

    channel.queue_bind(
        exchange=RABBITMQ_EXCHANGE, queue=queue_name, routing_key="#.image.#"
    )

    def callback(ch, method, properties, body):
        print("Received message from RabbitMQ...")
        message = json.loads(body)
        print(message)
        query_id = message["id"]
        image_base64 = message["images"]

        print("Converting base64 image to file...")
        image_path = save_image_from_base64(image_base64, query_id)
        if not image_path:
            print("Error saving image. Skipping processing.")
            return

        print("Checking image for harmful content...")
        print(f"Image path: {image_path}")

        harmful = check_image_harmful(image_path)

        print("Publishing results to RabbitMQ...")
        channel.basic_publish(
            exchange=RABBITMQ_EXCHANGE,
            routing_key="results",
            body=json.dumps(
                {
                    "id": query_id,
                    "service": "image_detection",
                    "results": {"harmful": harmful},
                }
            ),
        )

        print("Acknowledging message...")
        ch.basic_ack(delivery_tag=method.delivery_tag)

    channel.basic_consume(queue=queue_name, on_message_callback=callback)

    print("Image Detection Service started. Waiting for messages...")
    channel.start_consuming()


if __name__ == "__main__":
    main()
