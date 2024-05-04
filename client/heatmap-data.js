// server URL for heatmap data
const SERVER_URL = "https://aeroatlas.leisurerules.ca/heatmap-data.php";

export async function getHeatmapData() {

    try {
        const response = await fetch(SERVER_URL);
        console.debug(`heatmap data response === ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('ERROR - unable to fetch heatmap data', error);
        throw error;
    }
}
