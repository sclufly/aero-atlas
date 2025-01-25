<?php

    // allow requests from localhost
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    if (strpos($origin, 'localhost') !== false) {
        header("Access-Control-Allow-Origin: $origin");
    }

    class dbConnection {

        private $dbError;
        private $isConnected;
        private $adminName;
        private $adminPassword;
        private $dbName;
        private $host;
        private $conn;

        // constructor
        public function __construct() {
            $this->host = "";
            $this->adminName = "";
            $this->adminPass = "";
            $this->dbName = "";
        }
    
        // connect database
        public function connect() {

            // open connection
            if ( !$this->conn = mysqli_connect($this->host, $this->adminName, $this->adminPass, $this->dbName ) ) {
                echo mysqli_error($this->conn);
                throw new DBException ("ERROR: Unable to connect. ".mysqli_error($this->conn) );
            }
            
            $now = new DateTime();
            $mins = $now->getOffset() / 60;
            $sgn = ($mins < 0 ? -1 : 1);
            $mins = abs($mins);
            $hrs = floor($mins / 60);
            $mins -= $hrs * 60;
            $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);
            $query = "set time_zone='$offset'";
        
            if ( !$result = mysqli_query($this->conn, $query) ) {
                throw new DBException( "ERROR: Failed to set timezone. Query: ".$query." ".mysqli_error($this->conn));
            }
        }
    
        public function getDBConn( ) {
            return( $this->conn );
        }
    
        // get all heatmap data for the given time period
        public function getHeatmapData ( $time_period, &$heatmap_data ) {

            // round lat/lon to this many decimal points
            $dec = 3;

            // create mapping for time periods
            $time_intervals = array(
                0 => '30 MINUTE',
                1 => '1 HOUR',
                2 => '3 HOUR',
                3 => '12 HOUR',
                4 => '1 DAY',
            );

            // create query
            $query = "SELECT 
                        ROUND(C.lat, $dec) AS rounded_lat, ROUND(C.lon, $dec) AS rounded_lon, COUNT(*) AS point_count
                    FROM 
                        aero_trip_data C
                    JOIN 
                        aero_trip A ON C.trip_id = A.trip_id
                    WHERE 
                        A.start_time >= DATE_SUB((SELECT MAX(start_time) FROM aero_trip), INTERVAL {$time_intervals[$time_period]})
                    GROUP BY 
                        rounded_lat, rounded_lon;";

            // execute query and return response
            if ( !$result = mysqli_query($this->conn, $query) ) {
                throw new DBException( "Error in query : ".$query." ".mysqli_error($this->conn));
            }

            $heatmap_data = $result;
        }

        // format heatmap data into a JSON
        public function formatHeatmapData ( $heatmap_data ) {

            $formattedData = array();

            // construct the JSON object for each row
            while ($row = $heatmap_data->fetch_assoc()) {
                $lonlat = array(floatval($row['rounded_lon']), floatval($row['rounded_lat']));
                $count = intval($row['point_count']);
                $formattedData[] = array('lonlat' => $lonlat, 'count' => $count);
            }

            header("Content-Type: application/json");
            $jsonData = json_encode(array_values($formattedData), JSON_PRETTY_PRINT);
            echo $jsonData;
        }
    }

    $error = "OK";

    // get time period from request params & store it
    $time_period = htmlspecialchars($_GET["tp"]);
    
    // set up database object
    try {
        $db = new dbConnection();
        $db->connect();
    } catch ( Exception $e ) {
        $error = "ERROR: ".$e->getMessage()."\n";
    }
    
    // get heatmap data from the database
    if ( $error == "OK" ) {
        try {
            $db->getHeatmapData( $time_period, $heatmap_data );
            $db->formatHeatmapData( $heatmap_data );
        } catch ( Exception $e ) {
            $error = "ERROR: ".$e->getMessage()."\n";
        }
    }
?>