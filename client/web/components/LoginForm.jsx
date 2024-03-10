import React, { useEffect, useState } from "react";
import Link from "next/link";
import axios from "axios";
import { Toaster, toast } from "react-hot-toast";

const LoginForm = (props) => {
  const [password, setPassword] = useState("");
  const [email, setemail] = useState("");
  const url = "http://localhost:8000/api/login";
  useEffect(() => {
    console.clear();
  });
  const login = async (e) => {
    e.preventDefault();
    try {
      const resp = await axios.post(url, {
        email: email,
        password: password,
      });
      toast.success("Login Successful");
      if (resp.status === 200) {
        location.href = "/home";
      }
    } catch (error) {
      console.log(error);
      toast.error(error.response.data.error);
    }
  };

  return (
    <>
      <Toaster />
      <div className="text-center mt-24">
        <div className="flex items-center justify-center">
          <svg
            fill="none"
            viewBox="0 0 24 24"
            className="w-12 h-12 text-purple-400"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth="2"
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
            />
          </svg>
        </div>
        <h2 className="text-4xl tracking-tight">Sign in into your account </h2>
        <span className="text-sm">
          or{" "}
          <Link href="/register" className="text-pink-600">
            register a new account
          </Link>
        </span>
      </div>
      <div className="flex justify-center my-2 mx-4 md:mx-0">
        <form
          className="w-full max-w-xl bg-white rounded-lg shadow-md p-6"
          onSubmit={login}
        >
          <div className="flex flex-wrap -mx-3 mb-6">
            <div className="w-full md:w-full px-3 mb-6">
              <label
                className="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2"
                htmlFor="Password"
              >
                Email address
              </label>
              <input
                className="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none"
                type="email"
                value={email}
                onChange={(e) => setemail(e.target.value)}
                required
              />
            </div>
            <div className="w-full md:w-full px-3 mb-6">
              <label
                className="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2"
                htmlFor="Password"
              >
                Password
              </label>
              <input
                className="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            <div className="w-full flex items-center justify-between px-3 mb-3 ">
              <label htmlFor="remember" className="flex items-center w-1/2">
                <input
                  type="checkbox"
                  name=""
                  id=""
                  className="mr-1 bg-white shadow"
                />
                <span className="text-sm text-gray-700 pt-1">Remember Me</span>
              </label>
              <div className="w-1/2 text-right">
                <a href="#" className="text-slate-800 text-sm tracking-tight">
                  Forget your password?
                </a>
              </div>
            </div>
            <div className="w-full md:w-full px-3 mb-6">
              <button className="appearance-none block w-full bg-purple-600 text-gray-100 font-bold border border-gray-200 rounded-lg py-3 px-3 leading-tight transition hover:bg-pink-900 focus:outline-none focus:bg-white focus:border-gray-500">
                Sign in
              </button>
            </div>
          </div>
        </form>
      </div>
    </>
  );
};

export default LoginForm;
