# aero-atlas
[Aero Atlas](https://aeroatlas.leisurerules.ca/) is a collection of flight data from the Greater Toronto Area. Please visit the site to see an interactive display.

### Data Collection
I personally collect all flight data displayed in this project. I used FlightAware's [PiAware](https://www.flightaware.com/adsb/piaware/build) to create an ADS-B ground station, which is run via a Raspberry Pi. It uses a 1090 MHz antenna to receive real-time data directly from aircraft up to 400km away, and the [`dump1090`](https://github.com/flightaware/dump1090/blob/master/README-json.md) program to decode ADS-B radio signals. You can find more information about my ground station on my [FlightAware profile](https://www.flightaware.com/adsb/stats/user/sclufly).

I wrote `flight-data.py` to access this real-time data and send it to my database. `dump1090` does not provide all the information I require, such as origin and destination airports, so I run an additional web scrape using the `requests_html` library to collect this data.

### Database Tables
![image](https://github.com/sclufly/aero-atlas/assets/104785391/30be63a7-2bc7-4d5c-a476-3b1f291a4d4e)


### Next Steps
I have accumulated a wealth of data by running my data collection script every day over the past several months. I plan to run further analyses on this data, including creating heatmaps of common traffic patterns, sorting flights by origin/destination, and correlating variables such as altitude and air speed. I also want to create a live flight map in addition to my current historical flight map. This would allow users from across the world to view GTA skies in real time.

### Technologies Used
_Data Collection —_ Python, Raspberry Pi

_Backend —_ PHP, MySQL

_Frontend —_ JavaScript, HTML, CSS
