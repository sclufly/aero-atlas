import './map.css';
import flightsData from './fake_data.json';

import TileLayer from 'ol/layer/Tile';
import {Map, View, Feature} from 'ol';
import Heatmap from 'ol/layer/Heatmap';
import Vector from 'ol/source/Vector';
import Overlay from 'ol/Overlay.js';
import XYZ from 'ol/source/XYZ';
import Point from 'ol/geom/Point';
import {fromLonLat} from 'ol/proj';
import LineString from 'ol/geom/LineString.js';
import {Vector as VectorLayer} from 'ol/layer.js';
import {getVectorContext} from 'ol/render.js';
import {getWidth} from 'ol/extent.js';
import {Stroke, Style, Circle as CircleStyle, Fill} from 'ol/style.js';


// === CONSTANTS ===
const COLOURS = {
    bg: '#282828',
    bg0_h: '#1d2021',
    dark_red: '#cc241d',
    red: '#fb4934',
    bg0: '#282828',
    bg0_s: '#32302f',
    dark_green: '#98971a',
    green: '#b8bb26',
    bg1: '#3c3836',
    fg4: '#a89984',
    dark_yellow: '#d79921',
    yellow: '#fabd2f',
    bg2: '#504945',
    fg3: '#bdae93',
    dark_blue: '#458588',
    blue: '#83a598',
    bg3: '#665c54',
    fg2: '#d5c4a1',
    dark_purple: '#b16286',
    purple: '#d3869b',
    bg4: '#7c6f64',
    fg1: '#ebdbb2',
    dark_aqua: '#689d6a',
    aqua: '#8ec07c',
    dark_gray: '#928374',
    fg0: '#fbf1c7',
    gray: '#a89984',
    fg: '#ebdbb2',
    dark_orange: '#d65d0e',
    orange: '#fe8019'
};
const HYBRID_AIRPORTS = (() => {
    let oriAirports = new Set();
    let desAirports = new Set();

    flightsData.forEach(flight => { 
        oriAirports.add(flight.ori[2]);
        desAirports.add(flight.des[2]);
    });

    return oriAirports.intersection(desAirports)
})();

// === HEATMAP ===

var testData = {
    max: 50,
    data: []
};

var lon, lat, c;
for(var i=0; i<500; i++) {
    lon = Math.random() * (-79 + 80) - 80; // Random longitude between -180 and 180
    lat = Math.random() * (44 - 43.5) + 43.5;  // Random latitude between -85 and 85
    c = Math.floor(Math.random() * 50); // Random count between 0 and 49
    testData.data.push({ lonlat: [lon, lat], count: c });
}

var vectorSource = new Vector({
    features: testData.data.map(function(data) {
        var feature = new Feature({
            geometry: new Point(fromLonLat(data.lonlat)), // Convert lonlat to the map's projection
            weight: data.count // Set weight (count) for each point
        });
        return feature;
    })
});

var heatmapLayer = new Heatmap({
    source: vectorSource,
    blur: 15, // Set blur radius
    radius: 8, // Set radius size
    weight: function(feature) {
        return feature.get('weight'); // Get weight from each feature
    }
});
heatmapLayer.setVisible(false);



// === MAP BASE ===

// button functionality for changing the current map style
const mapStyleButton = document.getElementById('map-style-button');
const mapStyles = ["World_Terrain_Base", "World_Topo_Map", "World_Imagery"];
let currentMapStyle = mapStyles[0];

mapStyleButton.addEventListener('click', function () {
    const currentIndex = mapStyles.indexOf(currentMapStyle); 
    const nextIndex = (currentIndex + 1) % mapStyles.length;
    currentMapStyle = mapStyles[nextIndex];
    tileLayer.getSource().setUrl(`https://server.arcgisonline.com/ArcGIS/rest/services/${currentMapStyle}/MapServer/tile/{z}/{y}/{x}`);
});

// map style layer
var tileLayer = new TileLayer({
    source: new XYZ({
      url: `https://server.arcgisonline.com/ArcGIS/rest/services/${currentMapStyle}/MapServer/tile/{z}/{y}/{x}`,
      maxZoom: 19
    })
});

// button functionality for showing/hiding the heatmap layer
const heatmapButton = document.getElementById('heatmap-button');
let showHeatmap = false;

heatmapButton.addEventListener('click', function () {
    showHeatmap = !showHeatmap;
    heatmapLayer.setVisible(showHeatmap);
});

const view = new View({
    center: [-8800000, 5400000],
    zoom: 4
});

// map object
const map = new Map({
    target: 'map',
    layers: [tileLayer, heatmapLayer],
    view: view
});



// === FLIGHT DATA ===

const oriDesStyle = new Style({
    stroke: new Stroke({
        color: COLOURS.gray,
        width: 5,
        lineDash: [5, 7],
    }),
});

const oriDesFinishedStyle = new Style({
    stroke: new Stroke({
        color: COLOURS.dark_gray,
        width: 4,
        lineDash: [5, 7],
    }),
});

const interStyle = new Style({
    stroke: new Stroke({
        color: COLOURS.yellow,
        width: 5,
        lineDash: [5, 7],
    }),
});

const interFinishedStyle = new Style({
    stroke: new Stroke({
        color: COLOURS.yellow,
        width: 4,
        lineDash: [5, 7],
    }),
});

const oriAirportStyle = new Style({
    image: new CircleStyle({
        radius: 6,
        fill: new Fill({color: COLOURS.green}),
        stroke: new Stroke({
            color: COLOURS.dark_gray, width: 3
        })
    })
});

const desAirportStyle = new Style({
    image: new CircleStyle({
        radius: 6,
        fill: new Fill({color: COLOURS.red}),
        stroke: new Stroke({
            color: COLOURS.dark_gray, width: 3
        })
    })
});

const hybridAirportStyle = new Style({
    image: new CircleStyle({
        radius: 6,
        fill: new Fill({color: COLOURS.yellow}),
        stroke: new Stroke({
            color: COLOURS.dark_gray, width: 3
        })
    })
});

// create popup for airports
const popupOverlay = new Overlay({
    element: document.getElementById('popup'),
    autoPan: true,
    autoPanAnimation: {
        duration: 250
    }
});

// Event handler for click on airport feature
map.on('click', function(event) {
    let isAirportClicked = false;

    map.forEachFeatureAtPixel(event.pixel, function(feature) {
        if (feature.get('name')) {
            isAirportClicked = true;
            const coordinates = feature.getGeometry().getCoordinates();
            popupOverlay.setPosition(coordinates);
            const airportName = feature.get('name');
            document.getElementById('popup-content').innerHTML = airportName;
        }
    });

    if (!isAirportClicked) {
        popupOverlay.setPosition(undefined); // Hide the popup
    }
});

// Adjust the offset of the popup to move it above the feature
popupOverlay.setOffset([2, -10]); // Adjust the vertical offset as needed


// create flights
const flightsSource = new Vector({
    attributions: 'Base Maps from <a href="https://server.arcgisonline.com/ArcGIS/rest/services/">ArcGIS Online</a>, Flight Data from Sarah Cloughley',
    loader: function () {
        for (let i = 0; i < flightsData.length; i++) {
            const flight = flightsData[i];
            const ori = flight.ori;
            const des = flight.des;
            const inter = flight.inter || [];
            let crossesDateLine = false;
            let dateLineCrossIndex = 0;
            
            let allPoints = [ori, ...inter, des];
            let allGeometries = [];

            // create origin airport point
            const oriPoint = new Point(fromLonLat([ori[1], ori[0]]));
            const oriFeature = new Feature({
                geometry: oriPoint,
                name: ori[2],
                finished: true,
                airport: "origin",
            });

            // Iterate over each pair of consecutive points
            for (let j = 0; j < allPoints.length - 1; j++) {
                const fromPoint = allPoints[j];
                const toPoint = allPoints[j + 1];

                // Create an arc circle between the two locations
                const arcGenerator = new arc.GreatCircle(
                    {x: fromPoint[1], y: fromPoint[0]},
                    {x: toPoint[1], y: toPoint[0]}
                );

                // Generate the arc line
                const arcLine = arcGenerator.Arc(100, {offset: 10});
                
                // deal with dateline cross
                if (arcLine.geometries.length > 1) {
                    crossesDateLine = true;
                    dateLineCrossIndex = j || 1;
                }

                // Add arc coordinates to the concatenated points array
                allGeometries = allGeometries.concat(arcLine.geometries);
            }

            // paths which cross the -180°/+180° meridian are split
            // into two sections which will be animated sequentially
            const features = [oriFeature];
            allGeometries.map(function (geometry, i) {

                const line = new LineString(geometry.coords);
                line.transform('EPSG:4326', 'EPSG:3857');
                let isInter = (i !== 0 && i !== allGeometries.length - 1);
                const isLast = (i === allGeometries.length - 1);

                // check for dateline cross
                if (dateLineCrossIndex === i) {
                    isInter = false;
                }

                features.push(
                    new Feature({
                        geometry: line,
                        finished: false,
                        segmentCount: allGeometries.length,
                        isInter: isInter, 
                        isLast: isLast,
                    }),
                );
            });

            // create destination airport point
            const desPoint = new Point(fromLonLat([des[1], des[0]]));
            const desFeature = new Feature({
                geometry: desPoint,
                name: des[2],
                finished: false,
                airport: "destination",
            });
            features.push(desFeature);

            // add the features with a delay so that the animation
            // for all features does not start at the same time
            addLater(features, i * 500);
        }
        tileLayer.on('postrender', animateFlights);
    },
});
  
const flightsLayer = new VectorLayer({
    source: flightsSource,
    style: function (feature) {

        // if the animation is still active for a feature, do not
        // render the feature with the layer style
        if (feature.get('finished')) {

             // if it's a point, use the airport style
            if (feature.getGeometry().getType() === 'Point') {
                if (HYBRID_AIRPORTS.has(feature.get('name'))) {
                    return hybridAirportStyle;
                }
                return (feature.get('airport') === 'origin') ? oriAirportStyle : desAirportStyle;
            }

            if (feature.get('isInter')) {
                return interFinishedStyle;
            } else {
                return oriDesFinishedStyle;
            }
        }

        return null;
    },
});
  
const pointsPerMs = 0.02;
function animateFlights(event) {
    const vectorContext = getVectorContext(event);
    const frameState = event.frameState;
    const features = flightsSource.getFeatures();
    features.sort(function(first, second) {
        return first.ol_uid - second.ol_uid;
    });

    for (let i = 0; i < features.length; i++) {

        const feature = features[i];

        if (feature.get('isInter')) {
            vectorContext.setStyle(interStyle);
        } else {
            vectorContext.setStyle(oriDesStyle);
        }

        if (!feature.get('finished')) {

            // only draw the lines for which the animation has not finished yet
            const coords = feature.getGeometry().getCoordinates();
            const elapsedTime = frameState.time - feature.get('start');
            
            if (elapsedTime >= 0) {
                const segmentCount = feature.get('segmentCount');
                const elapsedPoints = elapsedTime * pointsPerMs * segmentCount;
        
                if (elapsedPoints >= coords.length) {
                    feature.set('finished', true);
                    // if this is a last segment, also set the next feature (destination airport) to be finished
                    if (feature.get('isLast')) {
                        features[i + 1].set('finished', true);
                    }
                }
        
                const maxIndex = Math.min(elapsedPoints, coords.length);
                const currentLine = new LineString(coords.slice(0, maxIndex));
        
                // animation is needed in the current and nearest adjacent wrapped world
                const worldWidth = getWidth(map.getView().getProjection().getExtent());
                const offset = Math.floor(map.getView().getCenter()[0] / worldWidth);
        
                // directly draw the lines with the vector context
                currentLine.translate(offset * worldWidth, 0);
                vectorContext.drawGeometry(currentLine);
                currentLine.translate(worldWidth, 0);
                vectorContext.drawGeometry(currentLine);
            }
        }
    }
    // continue the animation if there's at least one feature that's not finished
    if (!features.every(feature => feature.get('finished'))) {
        map.render();
    }
}
  
function addLater(features, timeout) {
    window.setTimeout(function () {
        let start = Date.now();
        features.forEach(function (feature) {
            feature.set('start', start);
            const segmentSum = feature.getGeometry().getCoordinates().length - 1;
            const duration = segmentSum / (pointsPerMs * (features.length - 2));
            start += duration;
        });
        flightsSource.addFeatures(features);
    }, timeout);
}

// add dynamic layers to the map
map.addLayer(flightsLayer);
map.addOverlay(popupOverlay);