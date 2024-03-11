import Navbar from "@/components/Navbar";
import SearchBar from "@/components/SearchBar";
import axios from "axios";
import { GlobalContext } from "@/context/GlobalContext";
import React, { useContext } from "react";

const home = () => {
  const { name } = useContext(GlobalContext);
  return (
    <div className="bg-purple-500 h-screen text-white">
      <Navbar />
      <SearchBar />
    </div>
  );
};

export default home;
export async function getServerSideProps(context) {
  try {
    const url = "http://localhost:8000/api/user-data";
    const cookie = context.req.cookies.at;
    const resp1 = await axios.get(url, { headers: { Cookie: `at=${cookie}` } });
    axios.defaults.headers.common[
      "Authorization"
    ] = `Bearer ${resp1.data.access_token}`;
    const email = resp1.data.email;

    const instance = axios.create({
      withCredentials: true,
    });
    const url2 = "http://localhost:8000/api/isLog";
    const resp = await instance.post(url2, {});
    if (resp.status !== 204) {
      return {
        redirect: {
          permanent: false,
          destination: "/",
        },
      };
    }
  } catch (error) {
    return {
      redirect: {
        permanent: false,
        destination: "/",
      },
    };
  }
  return { props: {} };
}
