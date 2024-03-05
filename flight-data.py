# taken from wiedehopf's comment here --- https://discussions.flightaware.com/t/reading-the-piaware-output-over-the-network/80571/10
# wiedehoph's github --- https://github.com/wiedehopf

from contextlib import closing
from urllib.request import urlopen, URLError
import json, threading, time

# store Waterloo IP
url="http://192.168.1.101/skyaware/data/aircraft.json"

def print_data():

    while 1:

        # get aircraft data
        with closing(urlopen(url, None, 5.0)) as aircraft_file:
            aircraft_data = json.load(aircraft_file)

        # print select data for each aircraft
        for a in aircraft_data['aircraft']:
            flight = a.get('flight')
            lat = a.get('lat')
            lon = a.get('lon')
            if lat and lon:
                print("Flight number: {flight} Latitude: {lat:.4f} Longitude: {lon:.4f}".format(flight=flight, lat=lat, lon=lon))

        print('=' * 61)
        time.sleep(5)

data_loop_thread = threading.Thread(target=print_data)
data_loop_thread.start()

