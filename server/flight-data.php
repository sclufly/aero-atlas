<?php
  
    class dbPDO extends PDO {
        private $dbError;
        private $isConnected;
        private $adminName;
        private $adminPassword;
        private $dbName;
        private $host;
        private $conn;
        private $engine;
        private $port;
    
        // constructor
        public function __construct() {
            $this->engine = "";
            $this->host = "";
            $this->dbName = "";
            $this->port = "";
            $this->adminName = "";
            $this->adminPassword = "";
            $dsn = $this->engine.":dbname=".$this->dbName.";host=".$this->host.";port=".$this->port;
            parent::__construct( $dsn, $this->adminName, $this->adminPassword );
            
            // set current date/time
            $now = new DateTime();
            $mins = $now->getOffset() / 60;
            $sgn = ($mins < 0 ? -1 : 1);
            $mins = abs($mins);
            $hrs = floor($mins / 60);
            $mins -= $hrs * 60;
            $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);
            $query = "set time_zone='$offset'";
        
            $query = $this->prepare( $query );
        
            if ( !$query->execute() ) {
                throw new DBException( "ERROR: Failed to set timezone. Query: ".$query );              
            }  
        
            $query->closeCursor();
        }

        // general query: select all
        private function selectAll( $table, $col, $val, $max = "" ) {

            // create query
            $query = "hold";
            if ( $max == "" ) {
                $query = $this->prepare( "SELECT * FROM $table WHERE $col = :v" );
            } else {
                $query = $this->prepare( "SELECT trip_id, $max FROM $table WHERE $col = :v ORDER BY $max DESC LIMIT 1" );
            }

            // bind parameters
            if ( !($query->bindParam( ':v', $val ) ) ) {
                throw new Exception( "Failed to bind parameter in selectAll. Query: ".$query );      
            }
            
            // execute query
            if ( !$query->execute() ) {
                throw new Exception( "Failed to execute query in selectAll. Query: ".$query );              
            }
            
            # return results
            if ( $max == "" ) {
                $rowArray = $query->fetchAll();
                return $rowArray;
            } else {
                $colArray = $query->fetch(PDO::FETCH_ASSOC);
                return $colArray;
            }
            
        }

        // general query: insert into
        private function insertInto( $table, $data ) {

            // create query
            $cols = implode(', ', array_keys($data));
            $vals = ':' . implode(', :', array_keys($data));
            $query = $this->prepare( "INSERT INTO $table ($cols) VALUES ($vals)" );

            // bind parameters
            $boundParams = array();
            foreach ($data as $column => $value) {
                if (!$query->bindParam(":".$column, $value)) {
                    throw new Exception("Failed to bind parameter in insertInto. Parameter: :$column");
                }
                $boundParams[":$column"] = $value;
            }

            // execute query
            if ( !$query->execute($boundParams) ) {
                $arr = $query->errorInfo();
                throw new Exception( "Failed to execute query in insertInto. Query: ".$query ); 
            }

            $query->closeCursor();
        }

        // insert data into aero_airplane_type
        private function insertAeroAirplaneType( $plane_type ) {

            // get current rows with this plane_type
            $rowArray = $this->selectAll( "aero_airplane_type", "plane_type", $plane_type );

            // if current plane_id is not already in the table
            if ( sizeof ( $rowArray ) == 0 ) {

                // add a new row to aero_airplane_type
                $data = array(
                    "plane_type" => $plane_type
                );
                $this->insertInto( "aero_airplane_type", $data);
            }
        }

        // insert data into aero_airplane
        private function insertAeroAirplane( $plane_id, $plane_type ) {

            // only add to the table if plane_type is not null
            if ( $plane_type ) {

                // get current rows with this plane_id
                $rowArray = $this->selectAll( "aero_airplane", "plane_id", $plane_id );

                // if current plane_id is not already in the table
                if ( sizeof( $rowArray ) == 0 ) {

                    // add a new row to aero_airplane
                    $data = array(
                        "plane_id" => $plane_id,
                        "plane_type" => $plane_type
                    );
                    $this->insertInto( "aero_airplane", $data );

                    // insert into aero_airplane_type
                    $this->insertAeroAirplaneType( $plane_type );
                }
            }
        }

        // insert data into aero_airport
        private function insertAeroAirport( $code, $city, $country ) {

            // get current rows with this code
            $rowArray = $this->selectAll( "aero_airport", "code", $code );

            // if current code is not already in the table
            if ( sizeof ( $rowArray ) == 0 ) {

                // add a new row to aero_airport
                $data = array(
                    "code" => $code,
                    "city" => $city,
                    "country" => $country
                );
                $this->insertInto( "aero_airport", $data);
            }
        }

        // insert data into aero_trip
        private function insertAeroTrip ( $plane_id, $flight_num, $ori_code, $ori_city, $ori_country, $des_code, $des_city, $des_country ) {

            // get the value of the latest start_time with this plane_id, as well as the trip_id
            $colArray = $this->selectAll( "aero_trip", "plane_id", $plane_id, "start_time" );
            $latest_start_time = $colArray["start_time"];
            $trip_id = $colArray["trip_id"];

            // get current time
            $start_time = date("Y-m-d H:i:s");
            $isNewTrip = false;

            // if the plane_id exists in the table, get the latest start_time
            if ( $latest_start_time ) {

                $first = new DateTime( $start_time );
                $last = new DateTime( $latest_start_time );

                // if the latest start_time was more than 5 hours ago, this is a new trip
                $diffSeconds = $first->getTimestamp() - $last->getTimestamp();
                if ( $diffSeconds >= 18000 ) {
                    $isNewTrip = true;
                }
            }

            // if current plane_id is not already in the table OR it's a new instance of an existing trip
            if ( !$latest_start_time || $isNewTrip ) {

                // add a new row to aero_airplane
                $data = array(
                    "plane_id" => $plane_id,
                    "start_time" => $start_time,
                    "flight_num" => $flight_num,
                    "ori_code" => $ori_code,
                    "des_code" => $des_code
                );
                $this->insertInto( "aero_trip", $data );

                // get the trip_id for the row that was just inserted
                $trip_id = $this->lastInsertId();

                // insert into aero_airport for both origin and destination
                $this->insertAeroAirport( $ori_code, $ori_city, $ori_country );
                $this->insertAeroAirport( $des_code, $des_city, $des_country );
            }

            // return the trip_id to be used in the aero_trip_data table
            return $trip_id;
        }

        // insert data into aero_trip_data
        private function insertAeroTripData( $trip_id, $lat, $lon, $alt, $speed, $roll, $heading, $squawk, $nav_modes ) {

            // get current time
            $curr_time = date("Y-m-d H:i:s");

            // add a new row to aero_airplane
            $data = array(
                "trip_id" => $trip_id,
                "curr_time" => $curr_time,
                "lat" => $lat,
                "lon" => $lon,
                "alt" => $alt,
                "speed" => $speed,
                "roll" => $roll,
                "heading" => $heading,
                "squawk" => $squawk,
                "nav_modes" => $nav_modes
            );
            $this->insertInto( "aero_trip_data", $data );
        }
    
        // create entry function
        public function createEntry( $data ) {

            // 1. insert data into aero_airplane & aero_airplane_type
            $this->insertAeroAirplane( $data["plane_id"], $data["plane_type"] );

            // 2. insert data into aero_trip & aero_airport
            $trip_id = $this->insertAeroTrip( $data["plane_id"], $data["flight_num"], 
                                              $data["ori_code"], $data["ori_city"], $data["ori_country"], 
                                              $data["des_code"], $data["des_city"], $data["des_country"] );
            echo $trip_id."\n";

            // 3. insert data into aero_trip_data
            $this->insertAeroTripData( $trip_id, $data["lat"], $data["lon"], $data["alt"], 
                                       $data["speed"], $data["roll"], $data["heading"], 
                                       $data["squawk"], $data["nav_modes"] );
        }
    
    }
    
    $error = "OK";
    $data_names = array(
        "flight_num", "plane_id", "lat", "lon", "alt", "speed", 
        "roll", "heading", "squawk", "nav_modes", "plane_type", 
        "ori_code", "ori_city", "ori_country", "des_code", 
        "des_city", "des_country"
    );
    
    // get plane data from request params & store them in $data
    $data = array();
    foreach ($data_names as $param) {
        $data[$param] = htmlspecialchars($_GET[$param]);
    }
    
    // set up database object
    try {
        $db = new dbPDO();
    } catch ( Exception $e ) {
        $error = "ERROR: ".$e->getMessage()."\n";
    }
    
    // create entry for the plane data
    if ( $error == "OK" ) {
        try {
            $db->createEntry( $data );
        } catch ( Exception $e ) {
            $error = "ERROR: ".$e->getMessage()."\n";
        }
    }
        
    echo $error;
?>