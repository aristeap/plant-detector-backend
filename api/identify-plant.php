<?php

    // This line requires the Composer autoload file. This file automatically
    // loads all the classes we need from the Guzzle library, so we don't have
    // to manually include each one. The `../` means "go up one directory" to
    // find the `vendor` folder, which is where the Guzzle library is located.
    require '../vendor/autoload.php';

    // We tell PHP to use these Guzzle classes. This allows us to use
    // short names like `Client` and `Utils` instead of their full namespaced paths.
    use GuzzleHttp\Client;
    use GuzzleHttp\Psr7\Utils;

    // Set your private API key and the project name.
    // Replace 'YOUR_API_KEY_HERE' with the actual key you got from Pl@ntNet.
    $apiKey = '2b10NnV1AeFtpB71MG7UTOlhQe';
    $project = 'all';

    // =========================================================================
    // This block handles the uploaded file and performs basic error checking.
    // =========================================================================

    // `$_FILES` is a superglobal array in PHP that holds information about
    // files uploaded via a POST request. We check if the 'image' key exists,
    // which is the name we gave the file in our React frontend's FormData.
    if (empty($_FILES['image'])) {
        // If no image was uploaded, we set the HTTP status code to 400 (Bad Request).
        http_response_code(400);
        // We send a JSON-formatted error message back to the frontend.
        echo json_encode(['message' => 'No image file uploaded.']);
        // We exit the script to stop further execution.
        exit;
    }

    // We get the details of the uploaded file and store them in variables.
    // 'tmp_name' is the temporary path where the file is stored on the server.
    // 'name' is the original filename on the user's computer.
    $uploadedFile = $_FILES['image'];
    $imagePath = $uploadedFile['tmp_name'];
    $imageName = $uploadedFile['name'];


    // =========================================================================
    // This block makes the API call to Pl@ntNet using the uploaded image.
    // =========================================================================

    try {
        // Create a new Guzzle HTTP client instance.
        $client = new Client(['base_uri' => 'https://my-api.plantnet.org/v2/']);

        // Make a POST request to the Pl@ntNet API.
        $response = $client->request('POST', "identify/{$project}", [
            // The 'query' array contains the URL parameters. We send our API key here.
            'query' => [
                'api-key' => $apiKey,
            ],
            // The 'multipart' array is used to send form data, including the file.
            'multipart' => [
                // This is the part for the image file.
                [
                    'name'     => 'images', // The name of the form field expected by the API.
                    'contents' => Utils::tryFopen($imagePath, 'r'), // The contents of the temporary file.
                    'filename' => $imageName // The original filename.
                ]
            ]
        ]);

        // Set the response header to tell the frontend that the content type is JSON.
        header('Content-Type: application/json');
        // Get the body of the response from Pl@ntNet and print it to the output.
        echo $response->getBody();

    } catch (Exception $e) {
        // If Guzzle throws an exception (e.g., a network error or a non-200 status code),
        // we catch it here.
        http_response_code(500);
        // We send a JSON-formatted error message back to the frontend.
        echo json_encode(['message' => 'Error: ' . $e->getMessage()]);
    }
?>