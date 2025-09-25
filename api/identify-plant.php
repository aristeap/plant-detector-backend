<?php
    header("Access-Control-Allow-Origin: https://plant-detector-project.netlify.app");

    // Add these additional headers for POST requests
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: POST, OPTIONS");

    // ADD THIS LINE
die("CORS headers successfully reached!");

    // This line requires the Composer autoload file.
    require '../vendor/autoload.php';

    // We tell PHP to use these Guzzle classes.
    use GuzzleHttp\Client;
    use GuzzleHttp\Psr7\Utils;
    // Remember to include the ClientException class here
    use GuzzleHttp\Exception\ClientException;

    //the api key from Pl@ntNet.
    $plantNetApiKey  = '2b10NnV1AeFtpB71MG7UTOlhQe'; // Make sure this is your actual PlantNet API key
    $plantNetProject = 'all';

    //the api key from perenual
    $perenualApiKey = 'sk-8kMx68bec490615a112277'; // Make sure this is your actual Perenual API key

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
        // Create a new Guzzle HTTP client instance for PlantNet.
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
        
        // Initialize perenualData to null (before the Perenual try-catch)
        $perenualData = null;

        // =========================================================================
        // NEW SECTION: Extract scientific name and call Perenual API
        // =========================================================================

        if (isset($plantNetResponse['results'][0]['species']['scientificName'])) {
            $scientificName = $plantNetResponse['results'][0]['species']['scientificNameWithoutAuthor'];

            // new guzzle client so it could make the call to the perenual API
            $perenualClient = new Client(['base_uri' => 'https://perenual.com/api/']);
            
            //****the free usage limit of the Perenual API only allows me up to 100 API calls a day *********//
            //that's why i will make it so if i am out of the limit i will only send back to the frontend the response from the plantnet API *****//
            try {
                // Use the scientific name to query the Perenual API.
                
                        //debugging**************************************************************************
                        error_log("Attempting Perenual API Call 1 (species-list) for: " . $scientificName);
                        //debugging**************************************************************************

                // FIRST API CALL to the perenual API (Species List)
                $perenualSearchResponse  = $perenualClient->request('GET', "v2/species-list", [
                    'query' => [
                        'key' => $perenualApiKey,
                        'q' => $scientificName
                    ]
                ]);
                
                // Decode the Perenual search response.
                $perenualSearchData  = json_decode($perenualSearchResponse->getBody(), true);
                        
                        //debugging**************************************************************************
                        error_log("Perenual Call 1 Raw Response: " . json_encode($perenualSearchData));
                        //debugging**************************************************************************


                // SECOND API CALL to the perenual API (Species Details)
                if (!empty($perenualSearchData['data'][0]['id'])) { 
                    $plantId = $perenualSearchData['data'][0]['id'];
                    
                            //debugging**************************************************************************
                            error_log("Perenual Plant ID found: " . $plantId);
                            error_log("Attempting Perenual API Call 2 (species/details) for ID: " . $plantId);
                            //debugging**************************************************************************


                    // Make the API call to the plants details based on the id:
                    $perenualDetailsResponse = $perenualClient->request('GET', 'species/details/' . $plantId, [
                        'query' => [ // Using 'query' for parameters is cleaner with Guzzle
                            'key' => $perenualApiKey
                        ]
                    ]);
                    $perenualData = json_decode($perenualDetailsResponse->getBody(), true);

                    

                    // --- START OF NEW CODE BLOCK for THIRD Perenual API Call (Care Guides) ---
                    // Ensure $perenualData exists, has the 'care-guides' key, and it's a string
                    if ($perenualData && isset($perenualData['care-guides']) && is_string($perenualData['care-guides'])) {
                        $careGuideUrl = $perenualData['care-guides'];
                        
                                //debugging**************************************************************************
                                error_log("Perenual Care Guide URL found: " . $careGuideUrl);
                                error_log("Attempting Perenual API Call 3 (care-guides) for URL: " . $careGuideUrl);
                                //debugging**************************************************************************

    
                        // THIRD API CALL to the perenual API (Care Guide List)
                        $perenualCareGuideResponse = $perenualClient->request('GET', $careGuideUrl); // Guzzle can handle full URLs
                        $perenualCareGuide = json_decode($perenualCareGuideResponse->getBody(), true);

                                //debugging**************************************************************************
                                error_log("Perenual Call 3 Raw Response (Care Guides): " . json_encode($perenualCareGuide));
                                //debugging**************************************************************************


                        // Initialize descriptions to null before the loop to avoid 'undefined variable' notices
                        $wateringDescription = null;
                        $sunlightDescription = null;
                        $pruningDescription = null;

                        // Check if care guide data and section exist
                        if ($perenualCareGuide && isset($perenualCareGuide['data'][0]['section'])) {
                            $sections = $perenualCareGuide['data'][0]['section'];

                                        //debugging**************************************************************************
                                        error_log("Care Guide Sections: " . json_encode($sections)); // Log sections
                                        //debugging**************************************************************************

                            foreach ($sections as $value) {
                                // Ensure 'type' and 'description' keys exist in each section item
                                if (isset($value['type']) && isset($value['description'])) {
                                    if ($value['type'] === 'watering') {
                                        $wateringDescription = $value['description'];
                                    } else if ($value['type'] === 'sunlight') {
                                        $sunlightDescription = $value['description'];
                                    } else if ($value['type'] === 'pruning') {
                                        $pruningDescription = $value['description'];
                                    } 
                                }
                            }
                            
                            // Now inject these new infos back into the perenualData response.
                            // Using descriptive names like '_details' and only if they were found.
                            if ($wateringDescription !== null) {
                                $perenualData['watering_details'] = $wateringDescription;
                            }
                            if ($sunlightDescription !== null) {
                                $perenualData['sunlight_details'] = $sunlightDescription;
                            }
                            if ($pruningDescription !== null) {
                                $perenualData['pruning_details'] = $pruningDescription;
                            }
                        }
                    }
                    // --- END OF NEW CODE BLOCK for THIRD Perenual API Call ---

                } // Closes the 'if (!empty($perenualSearchData['data'][0]['id']))' block
            } catch (ClientException $e) {
                // This catch block now handles errors from the Perenual species-list, species/details, AND care-guides calls
                if ($e->getResponse()->getStatusCode() === 429) {
                    // Do nothing. The perenualData variable remains null (or whatever it was before the error),
                    // and the script continues gracefully, sending only PlantNet data.
                    // You might want to log this error more robustly for debugging
                    error_log("Perenual API 429 (Too Many Requests) error: " . $e->getMessage());
                } else {
                    // If it's another error, re-throw it to be caught by the outer catch block.
                    throw $e;
                }
            }
            
            // Combine both API results into a single response object to send back to the frontend.
            $combinedResponse = [
                'plantNetData' => $plantNetResponse,
                'perenualData' => $perenualData // This will now include injected details if fetched
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