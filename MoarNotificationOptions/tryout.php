<?php
        try{
        $access_token="EAAPiOCKlTH8BAEn04RtF9oLY04rY5f5CEhGZCQNNpERYJY2oOtSDZAtBWnpQgzrV0Uzk6hWPYtuxVHqHBZAcYIeKvoWpolZCaoZCOJaP06x3jUOkTbF3jeZBTNZAUnc4aFEQNkRnp5LZBZAMDMVuHWDFecXr0rRRK9V4ZD";
        $userid = json_decode(file_get_contents("https://graph.facebook.com/me?fields=id&access_token=$access_token"))->id;
        //echo $userid;
        $app_access = false;
        if ($app_access === false) {
            $appid = "1093155657436287";
            $appsecret = "e4ccd71e9e826f5dbeee1a90d2f383a9";
            if ($appid && $appsecret) {
                $app_access = file_get_contents("https://graph.facebook.com/oauth/access_token?client_id=$appid&client_secret=$appsecret&grant_type=client_credentials");
                $app_access=substr($app_access,strpos($app_access,'=')+1);
                //echo $app_access;
            }
            if ($app_access) {
                $notification_url = "https://graph.facebook.com/$userid/notifications";
                $parameters = ["access_token" => $app_access,
                    "href" => "",
                    "template" => "There's a notification on the forum!"];
                $ch = curl_init();
//set the url, number of POST vars, POST data
                curl_setopt($ch, CURLOPT_URL, $notification_url);
                curl_setopt($ch, CURLOPT_POST, count($parameters));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

//execute post
                $result = curl_exec($ch);

//close connection
                curl_close($ch);
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            return ActivityModel::SENT_ERROR;
        }