from fastapi import FastAPI, HTTPException, File, UploadFile
from typing import Annotated
from pydantic import BaseModel
import time
import pika
import json
import asyncio
import uuid
import threading
import re

def extract_links(text):
    return re.findall(r"(https?://\S+)", text)

def generateUniqueId():
    return str(uuid.uuid4())

def generateQueueKey(payload: dict):
    key = "message" if payload.get("text") else "account"
    if payload.get("images"):
        key += ".image"
    if payload.get("text"):
        key += ".text"
        if extract_links(payload["text"]):
            key += ".link"
        elif payload["text"].strip() != "":
            key += ".text"
    return key

app = FastAPI()

start_time = time.time()


class Payload(BaseModel):
    id: str | None = None
    text: str | None = "Kill me please"
    images: str | None = None


EXCHANGE_NAME = "message_exchange"
RESULTS_QUEUE = "results_queue"
RESULTS_FILE_PATH = "results.json"
channel = None
data = []

services = [
    {
        "name": "profanity_detection",
        "categories": [
            "toxic",
            "severe_toxic",
            "obscene",
            "threat",
            "insult",
            "identity_hate",
        ],
        "types": ["message", "text"]
    },
    {
        "name": "link_detection",
        "categories": ["SCAM", "MALWARE", "IP_LOGGER", "NOHTTPS", "EXPLICIT"],
        "types": ["message", "text"]
    },
    {"name": "image_detection", "categories": ["HARMFUL"], "types": ["message", "image"]},
    {"name": "personal_info_detection", "types": ["message", "text"]},
    # {"name": "account_verification", "categories": ["is_valid"], "types": ["account"]},
]


async def waitForResults(
    id: str,
    return_on_any_harmful=True,
    return_all_results=True,
    services=(f["name"] for f in services),
):
    print(services)
    while True:
        print("--------------------")
        print("DATA::::")
        print(data)
        print("--------------------")

        if not data:
            print("Waiting for results...")
            await asyncio.sleep(1)
            continue

        result_dict = {"id": id, "services": {}}

        for result in data:
            print("Checking result:", result)
            if result["id"] == id:
                print("Found result for id:", id)
                for result_entry in result.get("results", []):
                    service_name = result_entry.get("service")
                    print("Service name:", service_name)
                    harmful = result_entry.get("result", {}).get("harmful", False)
                    print("Harmful:", harmful)

                    if return_all_results:
                        result_dict["services"][service_name] = result_entry.get(
                            "result"
                        )
                    else:
                        print("Returning only harmful results...")
                        print("Service name:", service_name)
                        result_dict["services"][service_name] = harmful
                    if harmful:
                        if return_on_any_harmful:
                            print("Harmful content detected. Returning results...")
                            return result_dict

        print("All results checked. Checking if all services have responded...")
        print("Services:", services)
        print("Result services:", result_dict["services"].keys())
        all_services_present = sorted(list(result_dict["services"].keys())) == sorted(
            services
        )
        print("All services present:", all_services_present)

        if all_services_present:
            print("All services have responded.")
            if return_all_results:
                print("Returning all results...")
                return result_dict
            else:
                return result_dict
        else:
            print("Waiting for more results...")
            await asyncio.sleep(1)
            continue


def callback(ch, method, properties, body):
    print("--------------------")
    print("Received message from results queue")
    print(json.loads(body.decode()))
    print("--------------------")

    message = json.loads(body.decode())
    if not message.get("service"):
        print("Invalid message received. Skipping...")
        return

    existing_entry = next(
        (entry for entry in data if entry["id"] == message["id"]), None
    )
    print("Existing entry:", existing_entry)

    if existing_entry:
        existing_entry["results"].append(
            {"service": message["service"], "result": message["results"]}
        )
    else:
        data.append(
            {
                "id": message["id"],
                "results": [
                    {"service": message["service"], "result": message["results"]}
                ],
            }
        )


def consumeResults(channel, queue_name):
    channel.basic_consume(queue=queue_name, on_message_callback=callback, auto_ack=True)
    channel.start_consuming()


async def startup_event():
    try:
        connection = pika.BlockingConnection(
            pika.ConnectionParameters(host="localhost")
        )
        global channel
        channel = connection.channel()

        channel.exchange_declare(exchange=EXCHANGE_NAME, exchange_type="topic")

        result = channel.queue_declare(queue="", exclusive=True)
        queue_name = result.method.queue

        channel.queue_bind(
            exchange=EXCHANGE_NAME, queue=queue_name, routing_key="results"
        )
        channel.queue_bind(
            exchange=EXCHANGE_NAME, queue=queue_name, routing_key="account_results"
        )

        threading.Thread(
            target=consumeResults,
            args=(
                channel,
                queue_name,
            ),
            daemon=True,
        ).start()
        print(f"Connected to direct exchange: {EXCHANGE_NAME}")

    except Exception as e:
        print("Error establishing connection to RabbitMQ:", e)


async def shutdown_event():
    try:
        if channel:
            channel.close()
            print("Channel closed")
    except Exception as e:
        print("Error closing channel:", e)


app.add_event_handler("startup", startup_event)
app.add_event_handler("shutdown", shutdown_event)


@app.get("/")
async def read_root():
    return {
        "message": "This is up and running",
        "status": "OK",
        "uptime": time.time() - start_time,
    }


@app.post("/check-message")
async def check_message(
    payload: Payload,
    return_on_any_harmful: bool = True,
    return_all_results: bool = True,
):
    try:
        print("Received payload:", payload)
        id = payload.id or generateUniqueId()
        text = payload.text
        images = payload.images

        if not text:
            raise HTTPException(
                status_code=400, detail="Text is required in the payload"
            )

        message = {"id": id, "text": text, "images": images}

        print("Publishing message to RabbitMQ...")
        key = generateQueueKey(payload.dict())
        print("Routing key:", key)
        channel.basic_publish(
            exchange=EXCHANGE_NAME,
            body=json.dumps(message).encode(),
            routing_key=key,
        )

        print("Message published to RabbitMQ")
        print("Waiting for results...")
        data.clear()
        result = await waitForResults(
            id,
            return_on_any_harmful,
            return_all_results,
            services=[f["name"] for f in services],
        )
        print("Returning results:", result)
        return result

    except Exception as e:
        print("Error publishing message to RabbitMQ:", e)
        raise HTTPException(status_code=500, detail="Internal Server Error")


@app.post("/check-account")
async def check_account(sender_details: dict, receiver_details: dict):
    try:
        id = generateUniqueId()
        message = {
            "id": id,
            "sender_details": sender_details,
            "receiver_details": receiver_details,
        }

        print("Publishing message to RabbitMQ...")
        channel.basic_publish(
            exchange=EXCHANGE_NAME,
            body=json.dumps(message).encode(),
            routing_key="account_validation",
        )

        print("Message published to RabbitMQ")
        print("Waiting for results...")
        data.clear()
        result = await waitForResults(
            id,
            return_on_any_harmful=False,
            return_all_results=True,
            services=[f["name"] for f in services],
        )
        print("Returning results:", result)
        return result

    except Exception as e:
        print("Error publishing message to RabbitMQ:", e)
        raise HTTPException(status_code=500, detail="Internal Server Error")
