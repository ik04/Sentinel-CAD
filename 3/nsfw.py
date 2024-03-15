import torch
from PIL import Image
from transformers import AutoModelForImageClassification, ViTImageProcessor

img = Image.open("yes1.webp")
model = AutoModelForImageClassification.from_pretrained(
    "Falconsai/nsfw_image_detection"
)
processor = ViTImageProcessor.from_pretrained("Falconsai/nsfw_image_detection")
with torch.no_grad():
    inputs = processor(images=img, return_tensors="pt")
    outputs = model(**inputs)
    logits = outputs.logits

predicted_label = logits.argmax(-1).item()
print("Predicted label:", predicted_label)
print(model.config.id2label[predicted_label])
