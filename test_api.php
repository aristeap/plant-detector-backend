<?php

// 1. We require the Composer autoload file. This file automatically
//    loads the Guzzle library (and its dependencies) that we installed
//    with `composer install`, so we don't have to manually `require`
//    each file.
require 'vendor/autoload.php';

// 2. We tell PHP that we'll be using the `Client` class from the `GuzzleHttp`
//    namespace and the `Utils` class from the `GuzzleHttp\Psr7` namespace.
//    This lets us use `new Client()` instead of the full class name.
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;


// Your private API key from Pl@ntNet.
$apiKey = '2b10NnV1AeFtpB71MG7UTOlhQe';

// The path to your test image file on your computer.
// For example, on Windows, this might look like: 'C:\\Users\\YourName\\Pictures\\test-plant.jpg'
$imagePath = 'plant_photo.jpg'; 

// NEW: We define the project in a variable.
$project = 'all';

// 4. Create a new Guzzle HTTP client. We set the `base_uri` to the
//    root of the Pl@ntNet API URL, so we don't have to type it every time.
$client = new Client(['base_uri' => 'https://my-api.plantnet.org/v2/']);

try {
    // 5. We use the client to make a POST request to the 'identify' endpoint.
    $response = $client->request('POST', "identify/{$project}", [
        
        // `query` is for URL parameters (like our API key and project).
        'query' => [
            // CHANGED: The 'project' parameter was moved from here.
            'api-key' => $apiKey,
        ],
        
        // `multipart` is used to send file data, which is how we'll send our image.
        'multipart' => [
            [
                'name'     => 'images', // The name of the form field for the image data.
                'contents' => Utils::tryFopen($imagePath, 'r'), // The contents of the image file.
                'filename' => basename($imagePath) // The original filename.
            ],
            [
                'name'     => 'organs', // The name of the form field for the organ (e.g., 'leaf').
                'contents' => 'flower' // The type of plant part shown in the image.
            ]
        ]
    ]);

    // 6. If the request is successful, we get the response body, which contains
    //    the JSON data from the API, and print it to the terminal.
    echo $response->getBody();

} catch (Exception $e) {
    // 7. If there's an error (e.g., wrong API key, file not found),
    //    we catch the exception and print an error message.
    echo 'Error: ' . $e->getMessage();
}