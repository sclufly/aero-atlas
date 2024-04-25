// server URL for historical data
const SERVER_URL = "https://aeroatlas.leisurerules.ca/historical-data.php";

export async function getHistoricalData(timePeriod) {

    // add params to server url
    const url = `${SERVER_URL}?tp=${timePeriod}`;

    try {
        const response = await fetch(url);
        console.debug(`historical data response === ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('ERROR - unable to fetch historical data', error);
        throw error;
    }
}
