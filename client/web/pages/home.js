import Navbar from "@/components/Navbar";
import SearchBar from "@/components/SearchBar";
import { GlobalContext } from "@/context/GlobalContext";
import React, { useContext, useEffect, useState } from "react";

const fetchData = async () => {
  try {
    const url = "http://localhost:8000/api/user-data";
    const resp1 = await fetch(url);
    const userData = await resp1.json();
    const accessToken = userData.access_token;

    const healthCheckResp = await fetch("http://127.0.0.1:8000/api/healthcheck", {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${accessToken}`,
      },
    });
    const healthCheckData = await healthCheckResp.json();
    console.log({ healthCheckData });

    if (healthCheckResp.status !== 204) {
      // Redirect logic goes here
    }
  } catch (error) {
    console.log({ error });
    // Redirect logic goes here
  }
};

const Home = () => {
  const { name } = useContext(GlobalContext);

  useEffect(() => {
    fetchData();
  }, []);

  return (
    <div className="bg-purple-500 h-screen text-white">
      <Navbar />
      <SearchBar />
    </div>
  );
};

export default Home;
