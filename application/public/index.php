<?php

session_start();

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client as HttpClient;

$query = "";

if (isset($_POST['query'])) {
    $query = $_POST['query'];
    $query = trim($query);
    $_SESSION['query'] = $query;
} else {
    if (isset($_SESSION['query'])) {
        $query = $_SESSION['query'];
    }
}

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>InfluxQL Query</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 12px;
        }

        textarea.form-control {
            border: 1px solid #656464;
        }

        .table th {
            background-color: rgb(12 66 144);
            color: white;
        }

        #query {
            width: 100%;
            height: 150px;
        }
    </style>
</head>

<body>

    <nav class="navbar bg-primary" data-bs-theme="dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">InfluxQL Query</span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="mb-3 mt-3">
            <form method="post">
                <div class="col-12 mb-2"><textarea id="query" name="query" rows="4" cols="50" class="form-control"><?php echo $query ?></textarea></div>
                <div class="col-12"><input type="submit" value="Submit" class="btn btn-primary"></div>
            </form>
        </div>
        <?php

        if (!isset($_ENV["INFLUXDB_SERVER_URI"]) || !isset($_ENV["INFLUXDB_SERVER_TOKEN"])) {
            echo "<div class='col-12'><b>Please set INFLUXDB_SERVER_URI and INFLUXDB_SERVER_TOKEN environment variables</b></div>";
            exit;
        }

        // Create an HTTP client
        $httpClient = new HttpClient([
            'base_uri' => $_ENV["INFLUXDB_SERVER_URI"],
            'headers' => [
                'Authorization' => 'Token ' . $_ENV["INFLUXDB_SERVER_TOKEN"],
                'Content-Type' => 'application/json'
            ]
        ]);

        $queryCheck = $query;
        $queryCheck = strtolower($queryCheck);
        if ($query == "") {
            echo "<div class='col-12'><b>Please enter a query</b></div>";
            exit;
        } else if (!str_starts_with($queryCheck, "select ")) {
            echo "<div class='col-12'><b>Only SELECT queries allowed.</b></div>";
            exit;
        }

        try {
            $startTime = microtime(true);
            // Execute the query
            $response = $httpClient->get('/query?pretty=true', [
                'query' => [
                    'pretty' => 'true',
                    'db' => 'trade',
                    'q' => $query
                ]
            ]);

            $endTime = microtime(true);
            // Calculate the duration in milliseconds
            $duration = ($endTime - $startTime) * 1000;

            echo "<div class='col-12 mb-1'><b>API database request time taken " . round($duration / 1000, 3) . " seconds</b></div>";

            // Get the response body
            $body = $response->getBody();
            $data = json_decode($body, true);

            if ($data["results"] > 0) {
                foreach ($data["results"] as $result) {

                    if (isset($result['error'])) {
                        echo "<h3>" . $result['error'] . "</h3>";
                    }
                    if (isset($result["series"]) && $result["series"] > 0) {
                        foreach ($result["series"] as $series) {
                            if (isset($series["tags"]) && count($series["tags"]) > 0) {
                                echo "<div class='col-12'>";
                                echo html_table($series["tags"]);
                                echo "</div>";
                            }

                            echo "<div class='col-12'>";
                            echo html_table($series["columns"], $series["values"]);
                            echo "</div>";
                        }
                    }
                }
            }

            // echo "<pre>";
            // print_r($data);
            // echo "</pre>";
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Display error message
            echo 'HTTP Request failed: ' . $e->getMessage();
        }

        ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>

<?php

function html_table($header = array(), $data = array())
{
    $headerCols = array();
    foreach ($header as $th) {
        $headerCols[] = "<th>" . $th . "</th>";
    }
    $th = "<tr>" . implode('', $headerCols) . "</tr>";

    $rows = array();
    foreach ($data as $row) {
        $cells = array();
        foreach ($row as $cell) {
            $cells[] = "<td>{$cell}</td>";
        }
        $rows[] = "<tr>" . implode('', $cells) . "</tr>";
    }
    return "<table class='table table-striped'>" . $th . implode('', $rows) . "</table>";
}
