import pika
import json
import random

RABBITMQ_HOST = "localhost"
RABBITMQ_EXCHANGE = "message_exchange"


def main():
    connection = pika.BlockingConnection(pika.ConnectionParameters(host=RABBITMQ_HOST))
    channel = connection.channel()

    channel.exchange_declare(exchange=RABBITMQ_EXCHANGE, exchange_type="topic")

    result = channel.queue_declare(queue="", exclusive=True)
    queue_name = result.method.queue

    channel.queue_bind(
        exchange=RABBITMQ_EXCHANGE, queue=queue_name, routing_key="account_validation"
    )

    def callback(ch, method, properties, body):
        message = json.loads(body)
        print("Received message from RabbitMQ...")
        query_id = message["id"]
        sender_details = message.get("sender_details", {})
        receiver_details = message.get("receiver_details", {})

        is_valid = random.choice([True, False])
        print("Account validation result:", is_valid)

        print("Publishing results to RabbitMQ...")
        channel.basic_publish(
            exchange=RABBITMQ_EXCHANGE,
            routing_key="account_results",
            body=json.dumps(
                {
                    "id": query_id,
                    "service": "account_validation",
                    "results": {"is_valid": is_valid},
                }
            ),
        )

        print("Acknowledging message...")
        ch.basic_ack(delivery_tag=method.delivery_tag)

    channel.basic_consume(queue=queue_name, on_message_callback=callback)

    print("Account Validation Service started. Waiting for messages...")
    channel.start_consuming()


if __name__ == "__main__":
    main()
