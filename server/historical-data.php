<?php


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
    
        // get all historical data for the given time period
        public function getHistoricalData ( $time_period, &$historical_data ) {

            $query ="SELECT 
                        A.trip_id,
                        B_ori.code AS ori_code,
                        B_des.code AS des_code,
                        B_ori.lat AS ori_lat,
                        B_ori.lon AS ori_lon,
                        B_des.lat AS des_lat,
                        B_des.lon AS des_lon,
                        C.lat AS inter_lat,
                        C.lon AS inter_lon
                    FROM 
                        aero_trip A
                    JOIN 
                        aero_airport_coords B_ori ON A.ori_code = B_ori.code
                    JOIN 
                        aero_airport_coords B_des ON A.des_code = B_des.code
                    JOIN
                        aero_trip_data C ON A.trip_id = C.trip_id
                    WHERE 
                        A.start_time < now()";

            // execute query
            if ( !$result = mysqli_query($this->conn, $query) ) {
                throw new DBException( "Error in query : ".$query." ".mysqli_error($this->conn));
            } else {
                
                // create double array and get rid of blank array element
                $historical_data = array ( array ( ));
                unset( $historical_data [ 0 ]);
                
                // add data to array
                while($row = mysqli_fetch_assoc($result)) {         
                    array_push( $historical_data, $row );
                }
            }
        }

        // format historical data into a JSON
        public function formatHistoricalData ( $historical_data ) {

            $formattedData = array();

            foreach ($historical_data as $entry) {
                $tripId = $entry["trip_id"];
            
                // initialize an array for this trip_id if not already initialized
                if (!isset($formattedData[$tripId])) {
                    $formattedData[$tripId] = array(
                        "ori" => array(floatval($entry["ori_lat"]), floatval($entry["ori_lon"]), $entry["ori_code"]),
                        "des" => array(floatval($entry["des_lat"]), floatval($entry["des_lon"]), $entry["des_code"]),
                        "inter" => array()
                    );
                }
            
                // add the intermediate point to the "inter" array if available
                if (!empty($entry["inter_lat"]) && !empty($entry["inter_lon"])) {
                    $formattedData[$tripId]["inter"][] = array(floatval($entry["inter_lat"]), floatval($entry["inter_lon"]));
                }
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
    
    // get historical data from the database
    if ( $error == "OK" ) {
        try {
            $db->getHistoricalData( $time_period, $historical_data );
            $db->formatHistoricalData( $historical_data );
        } catch ( Exception $e ) {
            $error = "ERROR: ".$e->getMessage()."\n";
        }
    }

    //print_r($historical_data);
    //echo $error;
?>