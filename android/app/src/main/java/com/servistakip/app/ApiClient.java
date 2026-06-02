package com.servistakip.app;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.OutputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

class ApiClient {
    static final String SERVER = "https://servistakippanel.com";

    static JSONObject post(String path, String token, JSONObject body) throws Exception {
        return request("POST", path, token, body);
    }

    static JSONObject get(String path, String token) throws Exception {
        return request("GET", path, token, null);
    }

    private static JSONObject request(String method, String path, String token, JSONObject body) throws Exception {
        URL url = new URL(SERVER + path);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod(method);
        conn.setConnectTimeout(15000);
        conn.setReadTimeout(20000);
        conn.setRequestProperty("Accept", "application/json");
        conn.setRequestProperty("Content-Type", "application/json; charset=utf-8");
        if (token != null && !token.isEmpty()) {
            conn.setRequestProperty("Authorization", "Bearer " + token);
        }
        if (body != null) {
            conn.setDoOutput(true);
            byte[] bytes = body.toString().getBytes(StandardCharsets.UTF_8);
            try (OutputStream os = conn.getOutputStream()) {
                os.write(bytes);
            }
        }

        int code = conn.getResponseCode();
        BufferedReader reader = new BufferedReader(new InputStreamReader(
            code >= 200 && code < 400 ? conn.getInputStream() : conn.getErrorStream(),
            StandardCharsets.UTF_8
        ));
        StringBuilder sb = new StringBuilder();
        String line;
        while ((line = reader.readLine()) != null) sb.append(line);
        JSONObject json = new JSONObject(sb.toString());
        if (code < 200 || code >= 400 || !json.optBoolean("success")) {
            throw new Exception(json.optString("message", "Sunucu hatasi"));
        }
        return json;
    }
}
