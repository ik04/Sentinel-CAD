import { data } from "autoprefixer"
import axios from "axios"

export default (io, socket) => {
  socket.on("join_room", (data) => {
    console.log(data)
    socket.join(data.room)
  })
  socket.on("createdMessage", (data) => {
    console.log(data)
    // socket.join(data.room)
    console.log(`${socket.id} joined ${data.room}`)
    socket.to(data.room).emit("newIncomingMessage", data)
  })
  socket.on("disconnect", (data) => {
    console.log("disconnect")
  })
}
// * success
