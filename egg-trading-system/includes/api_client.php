<?php

require_once __DIR__ . "/../config/api.php";

/*
|--------------------------------------------------------------------------
| API Client Helper
|--------------------------------------------------------------------------
| This file is used by the PHP Egg Trading System UI.
| All data operations should pass through the API layer.
|--------------------------------------------------------------------------
*/

function postToApi($endpoint, $data = [])
{
    if (!defined("API_BASE_URL")) {
        return [
            "status" => false,
            "message" => "API configuration is missing.",
            "data" => []
        ];
    }

    $url = rtrim(API_BASE_URL, "/") . "/" . ltrim($endpoint, "/");

    $ch = curl_init($url);

    if ($ch === false) {
        return [
            "status" => false,
            "message" => "Cannot connect to API.",
            "data" => []
        ];
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if (!empty($curlError)) {
        return [
            "status" => false,
            "message" => "Cannot connect to API. Please check if the API folder exists and Apache is running.",
            "data" => []
        ];
    }

    if ($response === false || trim($response) === "") {
        return [
            "status" => false,
            "message" => "No response from API.",
            "data" => []
        ];
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            "status" => false,
            "message" => is_array($decoded) && isset($decoded["message"])
                ? $decoded["message"]
                : "Request failed. Please try again.",
            "data" => []
        ];
    }

    if (!is_array($decoded)) {
        return [
            "status" => false,
            "message" => "Invalid response from API.",
            "data" => []
        ];
    }

    return $decoded;
}

function getFromApi($endpoint, $params = [])
{
    if (!defined("API_BASE_URL")) {
        return [
            "status" => false,
            "message" => "API configuration is missing.",
            "data" => []
        ];
    }

    $url = rtrim(API_BASE_URL, "/") . "/" . ltrim($endpoint, "/");

    if (!empty($params)) {
        $url .= "?" . http_build_query($params);
    }

    $ch = curl_init($url);

    if ($ch === false) {
        return [
            "status" => false,
            "message" => "Cannot connect to API.",
            "data" => []
        ];
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if (!empty($curlError)) {
        return [
            "status" => false,
            "message" => "Cannot connect to API. Please check if the API folder exists and Apache is running.",
            "data" => []
        ];
    }

    if ($response === false || trim($response) === "") {
        return [
            "status" => false,
            "message" => "No response from API.",
            "data" => []
        ];
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            "status" => false,
            "message" => is_array($decoded) && isset($decoded["message"])
                ? $decoded["message"]
                : "Request failed. Please try again.",
            "data" => []
        ];
    }

    if (!is_array($decoded)) {
        return [
            "status" => false,
            "message" => "Invalid response from API.",
            "data" => []
        ];
    }

    return $decoded;
}