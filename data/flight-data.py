import time, os, aiohttp, asyncio
from requests_html import AsyncHTMLSession
asession = AsyncHTMLSession()
from dotenv import load_dotenv
load_dotenv()


# store env variables
PI_URL = os.getenv('MARKHAM_PI_URL')
SERVER_URL=os.getenv('SERVER_URL')
HEADERS=os.getenv('HEADERS')
FLIGHT_URL=os.getenv('FLIGHT_URL')


# store constants
states = ['AL', 'AK', 'AS', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FM', 'FL', 'GA', 'GU', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MH', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'MP', 'OH', 'OK', 'OR', 'PW', 'PA', 'PR', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VI', 'VA', 'WA', 'WV', 'WI', 'WY'];
plane_type_xpath = '//div[contains(@class, "flightPageDataRow")]//div[contains(@class, "flightPageDataLabel") and contains(., "Aircraft Type")]/following-sibling::div[@class="flightPageData"]'


# renders the given url asyncronously for scraping
async def render_page(url):
    r = await asession.get(url)
    await r.html.arender()
    return r


# scrapes data from a FlightAware site
# data -- plane type, origin airport code/city/country, destination code/city/country
def get_online_plane_data(url):

    # get html from url
    try:
        # get the html of the page
        results_html = asession.run(
            lambda: render_page(url),
        )[0].html

        # find plane type info
        plane_type = results_html.xpath(plane_type_xpath, first=True).text
        plane_type = plane_type[:plane_type.find('(')].rstrip()

        # find origin info
        origin = results_html.find('.flightPageSummaryOrigin', first=True)
        origin_code = origin.find('.flightPageSummaryAirportCode', first=True).text
        origin_place = origin.find('.flightPageSummaryCity', first=True).text
        origin_city, origin_country = map(str.strip, origin_place.split(','))

        # find destination info
        dest = results_html.find('.flightPageSummaryDestination', first=True)
        dest_code = dest.find('.flightPageSummaryAirportCode', first=True).text
        dest_place = dest.find('.flightPageSummaryCity', first=True).text
        dest_city, dest_country = map(str.strip, dest_place.split(','))

        # replace states with "United States"
        if (origin_country in states):
            origin_country = "United States"
        if (dest_country in states):
            dest_country = "United States"
        
        return plane_type, origin_code, origin_city, origin_country, dest_code, dest_city, dest_country
    
    except:
        print("ERROR - unable to scrape data from", url)
        return None, None, None, None, None, None, None


# accesses data from the Raspberry PI site  
async def get_pi_data():
    try:
        # open an aiohttp session to get page
        session = aiohttp.ClientSession()
        async with session.get(PI_URL) as resp:
            json_data = await resp.json()
        await session.close()
        return json_data
    
    except:
        print("ERROR - unable to get PI data")
        return None


# accesses data from the Raspberry PI data for a specific plane
# data -- plane id hex, flight number, latitude, longitude, altitude, ground speed, roll, true heading, squawk, nav mode 
def get_pi_plane_data(a):

    # get variables
    plane_id = a.get('hex')
    alt = a.get('alt_baro')
    speed = a.get('gs')
    roll = a.get('roll')
    heading = a.get('true_heading')
    squawk = a.get('squawk')
    nav_modes = a.get('nav_modes')

    # convert nav_modes to a string
    if (nav_modes):
        nav_modes = ' '.join(nav_modes)

    return plane_id, alt, speed, roll, heading, squawk, nav_modes


def main():

    # loop through batches of planes
    while 1:

        # get the current data from the PI
        loop = asyncio.get_event_loop()
        pi_data = loop.run_until_complete(get_pi_data())

        # if there's no PI data, skip this batch
        if (not pi_data):
            continue

        print("*** this batch has ", len(pi_data['aircraft']), " planes ...")

        # for each plane, extract the relevant data and send it off
        for a in pi_data['aircraft']:

            flight_number = a.get('flight')
            lat = a.get('lat')
            lon = a.get('lon')

            # if there's not all of flight number/lat/lon, skip this plane
            if (not (flight_number and lat and lon)):
                continue

            # get the plane data from the PI
            plane_id, alt, speed, roll, heading, squawk, nav_modes = get_pi_plane_data(a)
            print("PI --- ", flight_number, plane_id, lat, lon, alt, speed, roll, heading, squawk, nav_modes)

            # get the plane data from online
            plane_type, origin_code, origin_city, origin_country, dest_code, dest_city, dest_country = get_online_plane_data(FLIGHT_URL + str(flight_number))
            print("ON --- ", plane_type, origin_code, origin_city, origin_country, dest_code, dest_city, dest_country)

            # send data to the server
            # userdata = {"flight": flight, "lat": lat, "lon": lon}
            # resp = requests.post(SERVER_URL, params=userdata)
            # print(resp)
            # print(resp.text)

        # wait 5 seconds until the next batch
        print('=' * 80)
        time.sleep(5)

main()
