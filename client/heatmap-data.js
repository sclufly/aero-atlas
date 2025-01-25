// server URL for heatmap data
const SERVER_URL = "https://aeroatlas.leisurerules.ca/heatmap-data.php";

export async function getHeatmapData(timePeriod) {

    // add params to server url
    const url = `${SERVER_URL}?tp=${timePeriod}`;

    try {
        const response = await fetch(url);
        console.debug(`heatmap data response === ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('ERROR - unable to fetch heatmap data', error);
        throw error;
    }
}
