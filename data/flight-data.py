import time, os, requests
from datetime import datetime, timedelta
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
seen_planes = {}
states = ['AL', 'AK', 'AS', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FM', 'FL', 'GA', 'GU', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MH', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'MP', 'OH', 'OK', 'OR', 'PW', 'PA', 'PR', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VI', 'VA', 'WA', 'WV', 'WI', 'WY'];
plane_type_xpath = '//div[contains(@class, "flightPageDataRow")]//div[contains(@class, "flightPageDataLabel") and contains(., "Aircraft Type")]/following-sibling::div[@class="flightPageData"]'


# renders the given url asyncronously for scraping
async def render_page(url):
    r = await asession.get(url)
    await r.html.arender()
    return r


# scrapes data from a FlightAware site
# data -- plane type, origin airport code/city/country, destination code/city/country
def get_online_plane_data(flight_num):

    # check if this plane has been seen in the last hour, and avoid web scraping if it has
    if ((flight_num in seen_planes) and (datetime.now() - seen_planes[flight_num][-1] < timedelta(hours=1))):
        plane_type, ori_code, ori_city, ori_country, des_code, des_city, des_country, curr_time = seen_planes[flight_num]
        return plane_type, ori_code, ori_city, ori_country, des_code, des_city, des_country
    
    # construct url
    url = FLIGHT_URL + str(flight_num)

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
        ori = results_html.find('.flightPageSummaryOrigin', first=True)
        ori_code = ori.find('.flightPageSummaryAirportCode', first=True).text
        ori_place = ori.find('.flightPageSummaryCity', first=True).text
        ori_city, ori_country = map(str.strip, ori_place.split(','))

        # find destination info
        des = results_html.find('.flightPageSummaryDestination', first=True)
        des_code = des.find('.flightPageSummaryAirportCode', first=True).text
        des_place = des.find('.flightPageSummaryCity', first=True).text
        des_city, des_country = map(str.strip, des_place.split(','))

        # replace states with "United States"
        if (ori_country in states):
            ori_country = "United States"
        if (des_country in states):
            des_country = "United States"

        # add the data to the seen planes hash map
        seen_planes[flight_num] = [plane_type, ori_code, ori_city, ori_country, des_code, des_city, des_country, datetime.now()]

        # return the data
        return plane_type, ori_code, ori_city, ori_country, des_code, des_city, des_country
    
    except:
        print("ERROR - unable to scrape data from", url)
        return None, None, None, None, None, None, None


# accesses data from the Raspberry PI site  
def get_pi_data():
    try:
        # get JSON data from the url
        response = requests.get(url=PI_URL)
        pi_data = response.json()
        return pi_data
    
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
    heading = a.get('mag_heading')
    squawk = a.get('squawk')
    nav_modes = a.get('nav_modes')

    # convert nav_modes to a string
    if (nav_modes):
        nav_modes = ' '.join(nav_modes)

    return plane_id, alt, speed, roll, heading, squawk, nav_modes


# sends data to the server
def send_data(flight_num, plane_id, lat, lon, alt, speed, roll, heading, squawk, nav_modes, plane_type, ori_code, ori_city, ori_country, des_code, des_city, des_country):
    
    # format data correctly 
    server_data = {key: value for key, value in locals().items() if key != "server_data"}

    # send data to the server
    response = requests.post(SERVER_URL, params=server_data)
    print(response)
    print(response.text)


def main():

    # loop through batches of planes
    while 1:

        # get the current data from the PI
        pi_data = get_pi_data()

        # if there's no PI data, skip this batch
        if (not pi_data):
            continue

        print("*** this batch has ", len(pi_data['aircraft']), " planes ...")

        # for each plane, extract the relevant data and send it off
        for a in pi_data['aircraft']:

            flight_num = a.get('flight')
            lat = a.get('lat')
            lon = a.get('lon')

            # if there's not all of flight number/lat/lon, skip this plane
            if (not (flight_num and lat and lon)):
                continue

            # get the plane data from the PI
            plane_id, alt, speed, roll, heading, squawk, nav_modes = get_pi_plane_data(a)
            print("PI --- ", flight_num, plane_id, lat, lon, alt, speed, roll, heading, squawk, nav_modes)

            # get the plane data from online
            plane_type, ori_code, ori_city, ori_country, des_code, des_city, des_country = get_online_plane_data(flight_num)
            print("ON --- ", plane_type, ori_code, ori_city, ori_country, des_code, des_city, des_country)

            # send data to the server
            # send_data(flight_num, plane_id, lat, lon, alt, speed, roll, heading, squawk, nav_modes, plane_type, ori_code, ori_city, ori_country, des_code, des_city, des_country)

        # wait 5 seconds until the next batch
        print('=' * 80)
        time.sleep(5)

main()

# send_data("RPA4556", "a0144e", "43.452464", "-79.164799", "12125", "333.4", "0.2", "124.3", "0527", "autopilot vnav tcas", "Embraer ERJ 175", "YYZ", "Toronto", "Canada", "LGA", "New York", "United States")
