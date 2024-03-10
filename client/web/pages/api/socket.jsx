import messageHandler from "@/utils/messageHandler";
import { Server } from "socket.io";

export default function SocketHandler(req, res) {
  // It means that socket server was already initialised
  if (res.socket.server.io) {
    console.log("Already set up");
    res.end();
    return;
  }

  console.log(res);
  const io = new Server(res.socket.server);
  res.socket.server.io = io;

  const onConnection = (socket) => {
    console.log("show time");
    messageHandler(io, socket);
  };

  // Define actions inside
  io.on("connection", onConnection);

  console.log("Setting up socket");
  res.end();
}
