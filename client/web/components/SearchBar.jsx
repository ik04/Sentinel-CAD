import axios from "axios";
import Link from "next/link";
import React, { useState } from "react";

const SearchBar = () => {
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [results, setResults] = useState([]);
  const searchUser = async (e) => {
    e.preventDefault();
    const url = "http://localhost:8000/api/search";
    if (search === "") {
      const resp = await axios.post(url, {});
      let searchResults = [];
      resp.data.forEach((element) => {
        searchResults.push(element);
      });
      setResults(searchResults);
    } else {
      // console.log(search)
      const resp = await axios.post(url, { search: search });
      // console.log(resp.data)
      let searchResults = [];
      resp.data.forEach((element) => {
        searchResults.push(element);
      });
      if (searchResults.length == 0) {
        setError("No Such user Found");
      }
      setResults(searchResults);
    }
  };

  return (
    <>
      <form className="mb-10 m-10" onSubmit={searchUser}>
        <label
          htmlFor="default-search"
          className="mb-2 text-sm font-medium text-gray-900 sr-only dark:text-white"
        >
          Search
        </label>
        <div className="relative">
          <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg
              aria-hidden="true"
              className="w-5 h-5 text-gray-500 dark:text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
              ></path>
            </svg>
          </div>
          <input
            type="text"
            id="default-search"
            className="block w-full p-4 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
            placeholder="Search Users"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <button className="text-white absolute right-2.5 bottom-2.5 bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-gray-600 dark:hover:bg-gray-900 dark:focus:ring-blue-800 transition">
            Search
          </button>
        </div>
      </form>

      {results.length != 0 ? (
        results.map((result, i) => {
          console.log(result.uuid);
          return (
            <ul
              role="list"
              className="p-6 divide-y bg-white  divide-slate-200 w-full"
            >
              <li className="flex py-4 first:pt-0 last:pb-0 ">
                <div className="ml-3 overflow-hidden " key={i}>
                  <Link href={{ pathname: `/chat/${result.uuid}` }}>
                    <p className="text-sm font-medium text-slate-900">
                      {result.name}
                    </p>
                  </Link>
                </div>
              </li>
            </ul>
          );
        })
      ) : (
        <p>{error}</p>
      )}
    </>
  );
};

export default SearchBar;
