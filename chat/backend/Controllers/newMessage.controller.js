import Users from "../models/users.js";
import Violation from "../models/violations.js";
import Conversations from "../models/conversations.js";
import Messages from "../models/messages.js";
import { getActiveUsers, io } from "../utility/socket.js";
import fs from "fs";
import path from "path";

const penalties = {
  profanity_detection: {
    toxic: 0.001,
    severe_toxic: 0.002,
    obscene: 0.015,
    threat: 0.02,
    insult: 0.01,
    identity_hate: 0.02,
  },
  link_detection: {
    SCAM: 0.05,
    MALWARE: 0.1,
    IP_LOGGER: 0.1,
    NOHTTPS: 0.05,
    EXPLICIT: 0.1,
  },
  image_detection: {
    HARMFUL: 0.2,
  },
};

const BLOCK_CREDIT = 0.2;

const IGNORED_PIIS = [
  "FIRSTNAME",
  "LASTNAME",
  "MIDDLENAME",
  "URL",
  "DATE",
  "TIME",
];

const newMessageController = async (req, res) => {
  const { recieverID } = req.params;
  const { _id: senderID } = req.user;
  const { message } = req.body;
  const force = req.body?.force || false;

  const user = await Users.findOne({ _id: senderID });

  if (user?.credits <= BLOCK_CREDIT) {
    console.log("You don't have enough credits to send message");
    return res.status(400).send({
      isError: true,
      error:
        "You have been blocked from sending messages for trying to send harmful content, you may appeal to get your credits back",
    });
  }

  console.log({ recieverID, senderID, message, force });

  if (message) {
    try {
      let conversations = await Conversations.findOne({
        usergroup: { $all: [senderID, recieverID] },
      });

      if (!conversations) {
        conversations = new Conversations({
          usergroup: [senderID, recieverID],
        });
      }

      let fileUrl = null;
      let fileBase64 = null;

      if (req.file) {
        fileUrl = req.file.filename;

        const imagePath = req.file.path;
        const imageData = fs.readFileSync(imagePath);
        fileBase64 = imageData.toString("base64");
      }

      const body = {
        text: message ? message : "",
      };

      if (fileBase64) {
        body.image = fileBase64;
      }

      console.log("message--->", message, force);
      console.log(message && !force, message, force, "message && !force");
      if (message && !force) {
        const pii = await fetch("http://127.0.0.1:8000/check-pii", {
          headers: {
            "Content-Type": "application/json",
          },
          method: "POST",
          body: JSON.stringify({ text: message }),
        });
        const pii_res = await pii.json();
        console.log({ pii_res: pii_res.ner });
        const filteredEntities = pii_res?.ner.filter(
          (entity) => !IGNORED_PIIS.includes(entity.entity_group)
        );
        console.log({ filteredEntities });
        if (filteredEntities?.length > 0) {
          return res.status(400).send({
            isPii: true,
            piiMessage: filteredEntities,
            messagePayload: {
              message,
            },
          });
        }
      }

      const checkMessage = await fetch(
        "http://127.0.0.1:8000/check-message?return_all_results=true",
        {
          headers: {
            "Content-Type": "application/json",
          },
          method: "POST",
          body: JSON.stringify(body),
        }
      );

      const checkMessageData = await checkMessage.json();
      console.dir(checkMessageData, { depth: null });
      if (checkMessageData) {
        let safe = { status: true, message: "Message is safe" };
        Object.entries(checkMessageData?.services).forEach(([item, value]) => {
          if (value?.harmful) {
            return (safe = {
              status: false,
              message: `Message contains harmful content, please try again with different message [${item}]`,
            });
          }
        });
        if (!safe.status) {
          if (user && checkMessageData.services) {
            const services = checkMessageData.services;
            let totalPenalty = 0;

            for (const service in services) {
              console.log("service--->", service);
              const categories = services[service];
              console.log("categories--->", categories);

              const violation = new Violation({
                userID: senderID,
                serviceName: service,
                filters: categories?.categories,
                messagePayload: {
                  message,
                },
              });
              console.log({ violation });
              await violation.save();
              console.log(
                "--------------violation saved-----------------------------------------"
              );
              for (const category of categories.categories) {
                console.log("category--->", category);
                if (penalties[service]?.[category] !== undefined) {
                  console.log(
                    "penalties[service][category]--->",
                    penalties[service][category]
                  );

                  totalPenalty += penalties[service][category];
                }
              }
            }

            console.log("totalPenalty--->", totalPenalty);

            totalPenalty = Math.round(totalPenalty * 100) / 100;

            user.credits -= totalPenalty;
            await user.save();
          }

          return res.status(400).send({
            isError: true,
            error:
              safe.message +
              ` [${Math.round(user.credits * 100)} credits remaining]`,
          });
        }
        console.log("checkMessageData--->", checkMessageData);
      }

      const newMessasge = new Messages({
        senderID,
        recieverID,
        messageText: message,
        fileUrl: fileUrl,
      });

      if (newMessasge) {
        conversations.messages.push(newMessasge._id);
      }

      const uploadDir = path.join("uploads");
      if (!fs.existsSync(uploadDir)) {
        fs.mkdirSync(uploadDir, { recursive: true });
      }

      await Promise.all([newMessasge.save(), conversations.save()]);

      // Check reciever online status
      const isRecieverActive = getActiveUsers(recieverID);
      console.log("isRecieverActive--->", isRecieverActive);
      // If user online then emi the newMessage event to socketID
      if (isRecieverActive)
        io.to(isRecieverActive).emit("newMessage", newMessasge);

      res.status(201).send({ newMessasge });
    } catch (e) {
      console.log("Error in new message controllers", e);
      res
        .status(400)
        .send({ isError: true, error: "Error in new message controllers" });
    }
  } else {
    res.status(400).send({ isError: true, error: "Invalid message" });
  }
};

export default newMessageController;
