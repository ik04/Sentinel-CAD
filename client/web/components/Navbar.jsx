import React, { useContext } from "react";
import axios from "axios";
import Link from "next/link";
import { GlobalContext } from "@/context/GlobalContext";
import { Toaster, toast } from "react-hot-toast";

const Navbar = (props) => {
  const { name } = useContext(GlobalContext);
  const handleLogout = async () => {
    try {
      const url = "http://localhost:8000/api/logout";
      const resp = await axios.post(url);
      toast.success("User Logged Out!");
      location.href = "/";
    } catch (error) {
      console.log(error.response);
    }
  };

  return (
    <div className="flex bg-purple-900 justify-between">
      <Toaster />
      <div className="logo mx-3 space-x-20 flex">
        <Link href={"/home"}>
          <h2 className="font-extralight  text-white h-14 p-1 w-14  text-4xl cursor-pointer">
            {name}
          </h2>
        </Link>
      </div>
      {/* <h2>Rooms</h2> */}

      <button
        className="cursor-pointer log text-white w-20  p-1  bg-purple-800  transition duration-300 hover:text-black hover:bg-white"
        onClick={handleLogout}
      >
        Logout
      </button>
    </div>
  );
};

export default Navbar;
