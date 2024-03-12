from contextlib import closing
from urllib.request import urlopen, URLError
import json, threading, time, requests

# store Waterloo IP & test server url
flight_url="http://192.168.1.101/skyaware/data/aircraft.json"
server_url = "https://www.leisurerules.ca/mouseTrap/test_php.php"

# basics taken from wiedehopf's comment here --- https://discussions.flightaware.com/t/reading-the-piaware-output-over-the-network/80571/10
# wiedehoph's github --- https://github.com/wiedehopf
def print_data():

    while 1:

        # get aircraft data
        with closing(urlopen(flight_url, None, 5.0)) as aircraft_file:
            aircraft_data = json.load(aircraft_file)

        # for each aircraft
        for a in aircraft_data['aircraft']:
            flight = a.get('flight')
            lat = a.get('lat')
            lon = a.get('lon')
            if lat and lon:

                # print data
                print("Flight number: {flight} Latitude: {lat:.4f} Longitude: {lon:.4f}".format(flight=flight, lat=lat, lon=lon))
                
                # send data to the server
                userdata = {"flight": flight, "lat": lat, "lon": lon}
                resp = requests.post(server_url, params=userdata)
                print(resp)
                print(resp.text)

        print('=' * 61)
        time.sleep(5)

data_loop_thread = threading.Thread(target=print_data)
data_loop_thread.start()

