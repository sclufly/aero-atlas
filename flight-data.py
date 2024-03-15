import json, threading, time
from contextlib import closing
from urllib.request import urlopen, URLError
from requests_html import AsyncHTMLSession
asession = AsyncHTMLSession()

# store urls
pi_url="http://192.168.1.101/skyaware/data/aircraft.json"
server_url="https://www.leisurerules.ca/mouseTrap/test_php.php"
headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3'}
flight_url="https://www.flightaware.com/live/flight/"

# renders the given url asyncronously for scraping
async def render_page(url):
    r = await asession.get(url)
    await r.html.arender()
    return r

# scrapes origin, destination, and aircraft type from a FlightAware site
def get_online_flight_data(url):

    # get the html of the page
    results_html = asession.run(
        lambda: render_page(url),
    )[0].html

    try:
        origin_city = results_html.find('span.flightPageSummaryCity', first=True).text
        destination_city = results_html.find('span.destinationCity', first=True).text
        plane_type = results_html.find('div.flightPageData', first=True).text
        return origin_city, destination_city, plane_type
    except:
        return None, None, None

# accesses data from the Raspberry PI site   
def get_pi_flight_data():
    with closing(urlopen(pi_url, None, 5.0)) as aircraft_file:
        return json.load(aircraft_file)

def main():

    while 1:
        aircraft_data = get_pi_flight_data()

        for a in aircraft_data['aircraft']:
            flight = a.get('flight')
            lat = a.get('lat')
            lon = a.get('lon')
            orig = None
            dest = None
            type = None

            # get data from the web
            if flight:
                orig, dest, type = get_online_flight_data(flight_url + str(flight))

            if lat and lon:

                # print data
                print("Flight number: {flight} Latitude: {lat:.4f} Longitude: {lon:.4f} Origin: {orig} Destination: {dest} Plane Type: {type}".format(flight=flight, lat=lat, lon=lon, orig=orig, dest=dest, type=type))
                
                # send data to the server
                # userdata = {"flight": flight, "lat": lat, "lon": lon}
                # resp = requests.post(server_url, params=userdata)
                # print(resp)
                # print(resp.text)

        print('=' * 61)
        time.sleep(5)

main()

# data_loop_thread = threading.Thread(target=start_async_main)
# data_loop_thread.start()
# data_loop_thread.join()