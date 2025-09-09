<?php

    // This line requires the Composer autoload file.
    require '../vendor/autoload.php';

    // We tell PHP to use these Guzzle classes.
    use GuzzleHttp\Client;
    use GuzzleHttp\Psr7\Utils;
    // Remember to include the ClientException class here
    use GuzzleHttp\Exception\ClientException;

    //the api key from Pl@ntNet.
    $plantNetApiKey  = '2b10NnV1AeFtpB71MG7UTOlhQe';
    $plantNetProject = 'all';

    //the api key from perenual
    $perenualApiKey = 'sk-8kMx68bec490615a112277';

    // =========================================================================
    // This block handles the uploaded file and performs basic error checking.
    // =========================================================================

    if (empty($_FILES['image'])) {
        http_response_code(400);
        echo json_encode(['message' => 'No image file uploaded.']);
        exit;
    }

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
        $response = $client->request('POST', "identify/{$plantNetProject}", [
            'query' => [
                'api-key' => $plantNetApiKey,
            ],
            'multipart' => [
                [
                    'name'     => 'images',
                    'contents' => Utils::tryFopen($imagePath, 'r'),
                    'filename' => $imageName
                ]
            ]
        ]);

        // Get the body of the response from Pl@ntNet.
        $plantNetResponse = json_decode($response->getBody(), true);
        
        // Initialize perenualData to null
        $perenualData = null;

        // =========================================================================
        // NEW SECTION: Extract scientific name and call Perenual API
        // =========================================================================

        if (isset($plantNetResponse['results'][0]['species']['scientificName'])) {
            $scientificName = $plantNetResponse['results'][0]['species']['scientificNameWithoutAuthor'];

            //new guzzle client so it could make the call to the perenual API
            $perenualClient = new Client(['base_uri' => 'https://perenual.com/api/']);
            
            //****the free usage limit of the Perenual API only allows me up to 100 API calls a day *********//
            //that's why i will make it so if i am out of the limit i will only send back to the frontend the response from the plantnet API *****//
            try {
                // Use the scientific name to query the Perenual API.
                $perenualSearchResponse  = $perenualClient->request('GET', "v2/species-list", [
                    'query' => [
                        'key' => $perenualApiKey,
                        'q' => $scientificName
                    ]
                ]);
                
                // Decode the Perenual response.
                $perenualSearchData  = json_decode($perenualSearchResponse->getBody(), true);

                //NOW to get the plant's details we need to make another API call based on the plant's id (which we retrieve from the first API call to the perenual)
                if (!empty($perenualSearchData['data'][0]['id'])) {
                    $plantId = $perenualSearchData['data'][0]['id'];
                    //make the api call to the plants details based on the id:
                    $perenualDetailsResponse = $perenualClient->request('GET', 'species/details/' . $plantId . '?key=' . $perenualApiKey);
                    $perenualData = json_decode($perenualDetailsResponse->getBody(), true);
                }
            } catch (ClientException $e) {
                // Check if the error is specifically a '429 Too Many Requests'
                if ($e->getResponse()->getStatusCode() === 429) {
                    // Do nothing. The perenualData variable remains null,
                    // and the script continues gracefully.
                } else {
                    // If it's another error, re-throw it to be caught by the outer catch block.
                    throw $e;
                }
            }
        
            // Combine both API results into a single response object to send back to the frontend.
            $combinedResponse = [
                'plantNetData' => $plantNetResponse,
                'perenualData' => $perenualData
            ];

            // Set the response header to tell the frontend that the content type is JSON.
            header('Content-Type: application/json');
            echo json_encode($combinedResponse);

        } else {
            // If PlantNet didn't return a scientific name, just send the original response
            // so the frontend can display an error message.
            header('Content-Type: application/json');
            echo json_encode($plantNetResponse);
        }

    } catch (Exception $e) {
        // This outer catch block will handle all other unexpected errors
        // and send a 500 status. The inner block prevents it from being
        // triggered by the 429 error.
        http_response_code(500);
        echo json_encode(['message' => 'Server Error: ' . $e->getMessage()]);
    }
?>