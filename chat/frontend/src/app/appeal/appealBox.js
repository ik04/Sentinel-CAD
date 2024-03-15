import { useEffect, useRef, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import Image from "next/image";
import moment from "moment";
import {
  getAllMessages,
  sendNewMessage,
  suppressPiiMessage,
  resetErrorState,
} from "../redux/slice/messagesSlice";
import Skeleton from "../components/skeleton";
import Alert from "../components/alertToast";
import ScammerWarning from "../components/warning";
import {
  getAllViolations,
  setViolationAppeal,
  createViolationAppeal,
} from "../redux/slice/violationSlice";

const ViolationCard = ({ violation }) => {
  const [appealMessage, setAppealMessage] = useState("");
  const dispatch = useDispatch();

  const handleAppeal = () => {
    dispatch(
      createViolationAppeal({
        violationId: violation?._id,
        appealMessage,
        userId: violation.userID,
      })
    );
  };

  return (
    <div className="bg-gray-800 shadow-md rounded-md overflow-hidden flex flex-col">
      <div className="px-4 py-5">
        <div className="flex items-center mb-2">
          <p className="text-base font-bold text-white">ID:</p>
          <p className="text-base text-gray-300 ml-2">{violation?._id}</p>
        </div>
        <div className="flex items-center mb-2">
          <p className="text-base font-bold text-white">User ID:</p>
          <p className="text-base text-gray-300 ml-2">{violation?.userID}</p>
        </div>
        <div className="flex items-center mb-2">
          <p className="text-base font-bold text-white">Service Name:</p>
          <p className="text-base text-gray-300 ml-2">
            {violation?.serviceName}
          </p>
        </div>
        <div className="flex items-center mb-2">
          <p className="text-base font-bold text-white">Filters:</p>
          <p className="text-base text-gray-300 ml-2">
            {violation?.filters?.join(", ")}
          </p>
        </div>
        <div className="flex items-center mb-2">
          <p className="text-base font-bold text-white">Status:</p>
          <p className="text-base text-gray-300 ml-2">{violation?.status}</p>
        </div>
        <div className="flex items-center mb-2">
          <p className="text-base font-bold text-white">Message:</p>
          <p className="text-base text-gray-300 truncate ml-2">
            {violation?.messagePayload?.message}
          </p>
        </div>
        <div className="flex items-center mb-2">
          <p className="text-base font-bold text-white">Created At:</p>
          <p className="text-base text-gray-300 ml-2">{violation?.createdAt}</p>
        </div>
      </div>
      {violation.appealResponse ? (
        <div className="">{violation.appealResponse.reason}</div>
      ) : (
        <div className="flex px-4 py-4 border-t border-gray-700 gap-2">
          <textarea
            className="input w-full"
            placeholder="Enter your appeal message..."
            value={appealMessage}
            onChange={(e) => setAppealMessage(e.target.value)}
          />
          <button
            className="btn"
            onClick={handleAppeal}
            disabled={!appealMessage}
          >
            Appeal
          </button>
        </div>
      )}
    </div>
  );
};

const ChatBox = () => {
  const [message, setMessage] = useState("");
  const [selectedFile, setSelectedFile] = useState(null);
  const handleFileSelect = (e) => {
    setSelectedFile(e.target.files[0]);
  };

  let { violations, isLoading, errorMessage, isError, selectedViolation } =
    useSelector((state) => state.violation);

  console.log({
    violations,
    isLoading,
    errorMessage,
    isError,
    selectedViolation,
  });

  const { userInfo } = useSelector((state) => state.user);
  const dispatch = useDispatch();

  const handleSendMessge = () => {
    const recieverID = onGoingUserChat?._id;
    if (message)
      dispatch(sendNewMessage({ message, recieverID, selectedFile }));
  };

  const handleForceSendMessge = () => {
    console.log({ message });
    const recieverID = onGoingUserChat?._id;
    if (message)
      dispatch(
        sendNewMessage({
          message: messagePayload?.message,
          recieverID,
          selectedFile,
          force: true,
        })
      );
    dispatch(suppressPiiMessage());
  };

  let {
    onGoingUserChat,
    chats,
    isMessageSending,
    isPii,
    piiMessage,
    messagePayload,
  } = useSelector((state) => state.message);

  return (
    <div className="px-4 w-full">
      {selectedViolation ? (
        <div className="flex flex-col h-full">
          <div className="my-3 chat-window">
            <ViolationCard violation={selectedViolation} />
          </div>
        </div>
      ) : (
        <div className="h-full form-control justify-center items-center">
          <div className="text-2xl text-center">{`Welcome ${userInfo?.name} to the new Chat App`}</div>
          <div className="mt-4 flex justify-center text-center text-sm">
            Catch up with all your direct messages, group chats and spaces - all
            in one place
          </div>
        </div>
      )}
      <Alert
        isAlertVisible={isError}
        alertText={errorMessage}
        clickHandler={() => dispatch(resetErrorState())}
      />
    </div>
  );
};

export default ChatBox;
