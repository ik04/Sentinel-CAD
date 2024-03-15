from collections import Counter
import cv2
from PIL import Image
import io
import base64
import PyPDF2
from fastapi import FastAPI, HTTPException, File, UploadFile
from pydantic import BaseModel
from typing import List
import time
import pika
import json
import asyncio
import uuid
import threading
import re
import tempfile
import os

import numpy as np
from transformers import pipeline

loaded_gen = pipeline(
    "token-classification", "models/personal_identification_info_detection/model/"
)

import joblib
import numpy as np

scaler = joblib.load("models/spam_account_detection/models/scaler.pkl")
model = joblib.load(
    "models/spam_account_detection/models/logistic_regression_model.pkl"
)


def extract_text_from_pdf(pdf_file_path: UploadFile = File(...)):
    text = ""
    old_text = ""
    with open(pdf_file_path.filename, "wb") as file_object:
        file_object.write(pdf_file_path.file.read())
    with open(pdf_file_path.filename, "rb") as file:
        pdf_reader = PyPDF2.PdfReader(file)
        num_pages = len(pdf_reader.pages)
        for page_num in range(num_pages):
            page = pdf_reader.pages[page_num]
            old_text += page.extract_text()
            text += page.extract_text().replace("\n", "").strip()
    return text

def spam_forward(input):
    columns_to_standardize = [2, 3, 4, 7, 8]
    input = np.array([input])
    data_to_transform = input[:, columns_to_standardize]
    transformed_data = scaler.transform(data_to_transform)
    standardized_input = input.copy()
    standardized_input[:, columns_to_standardize] = transformed_data
    predictions = model.predict_proba(standardized_input)
    spam_pred = predictions[0][1]
    return spam_pred


def extract_links(text):
    return re.findall(r"(https?://\S+)", text)


def generateUniqueId():
    return str(uuid.uuid4())


def generateQueueKey(payload: dict):
    key = "message" if payload.get("text") else "account"
    if payload.get("image"):
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
    text: str | None = "Kill me please http://pornhub.com"
    image: str | bool | None = None


class SpamPayload(BaseModel):
    features: List[str]
    regular: List[str]
    spam: List[str]
    pick: str
    # features: List[str]
    # regular: List[float]
    # spam: List[float]

class PII(BaseModel):
    text: str | None = "John Smith (applicant for Software Engineer position) recently applied for a job at TechCorp. His email address is john.smith@email.com and phone number is (555) 555-5555. In his resume, he mentioned his experience with Python and Java"

EXCHANGE_NAME = "message_exchange"
RESULTS_QUEUE = "results_queue"
RESULTS_FILE_PATH = "results.json"
channel = None
data = []
pii_data = []

all_services = services = [
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
        "types": ["text"],
    },
    {
        "name": "link_detection",
        "categories": ["SCAM", "MALWARE", "IP_LOGGER", "NOHTTPS", "EXPLICIT"],
        "types": ["link"],
    },
    {
        "name": "image_detection",
        "categories": ["HARMFUL"],
        "types": ["image"],
    },
]
    # {"name": "personal_info_detection", "types": ["message", "text"]},
    # {"name": "account_verification", "categories": ["is_valid"], "types": ["account"]},


def read_txt_data(nsfw=False):
    print("nsfw:", nsfw, type(nsfw))
    file_path = None
    if nsfw == "True":
        file_path = f"data/images/nsfw.txt"
    else:
        file_path = f"data/images/non.txt"
    print("file_path:", file_path)
    try:
        with open(file_path, "r") as file:
            txt_data = file.read()
        return txt_data
    except FileNotFoundError:
        return "File not found."


async def waitForResults(
    id: str,
    return_on_any_harmful=True,
    return_all_results=True,
    routing_key="message",
    services=[],
):
    keys = list(set(routing_key.split(".")))

    print("Keys:", keys)
    relevant_services = []

    print("ser:", services)

    if len(services) == 0:
        for service in all_services:
            print("Service:", service)
            for key in keys:
                print("Key:", key)
                if key in service["types"]:
                    print("Service is relevant:", service)
                    relevant_services.append(service["name"])
                    break
    services = relevant_services
    print("Services-o:", services)
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
        all_services_present = sorted(list(result_dict["services"].keys())) <= sorted(
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

    service = message["service"]
    print("Service:", service)

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
            exchange=EXCHANGE_NAME, queue=queue_name, routing_key="pii_results"
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
        image = payload.image
        print("Image:", image)
        image = read_txt_data(nsfw=image)

        if not text:
            raise HTTPException(
                status_code=400, detail="Text is required in the payload"
            )

        message = {"id": id, "text": text, "image": image}

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
            routing_key=key
        )
        print("Returning results:", result)
        return result

    except Exception as e:
        print("Error publishing message to RabbitMQ:", e)
        raise HTTPException(status_code=500, detail="Internal Server Error")


@app.post("/extract-text/")
async def get_text_from_pdf(pdf_file: UploadFile = File(...)):
    text = extract_text_from_pdf(pdf_file)
    id = generateUniqueId()
    print(id)
    message = {"text": text, "id": id}
    print(message)
    print("Publishing message to RabbitMQ...")
    channel.basic_publish(
        exchange=EXCHANGE_NAME,
        body=json.dumps(message).encode(),
        routing_key="message.text",
    )
    print("Message published to RabbitMQ")
    print("Waiting for results...")
    data.clear()
    result = await waitForResults(
        id,
        services=["profanity_detection"],
    )
    print("Returning results:", result)
    return result
