import './map.css';
import flightsData from './fake_data.json';

import TileLayer from 'ol/layer/Tile';
import {Map, View, Feature} from 'ol';
import Heatmap from 'ol/layer/Heatmap';
import Vector from 'ol/source/Vector';
import XYZ from 'ol/source/XYZ';
import Point from 'ol/geom/Point';
import {fromLonLat} from 'ol/proj';
import LineString from 'ol/geom/LineString.js';
import {Vector as VectorLayer} from 'ol/layer.js';
import {getVectorContext} from 'ol/render.js';
import {getWidth} from 'ol/extent.js';
import {Stroke, Style} from 'ol/style.js';



// === HEATMAP ===

var tileLayer = new TileLayer({
    source: new XYZ({
      url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      maxZoom: 19
    })
  });

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



// === MAP BASE ===

const view = new View({
    center: [-8800000, 5400000],
    zoom: 7
});

const map = new Map({
    target: 'map',
    layers: [tileLayer, heatmapLayer],
    view: view
});



// === FLIGHT DATA ===

const oriDesStyle = new Style({
    stroke: new Stroke({
        color: '#ffffff',
        width: 4,
    }),
});

const oriDesFinishedStyle = new Style({
    stroke: new Stroke({
        color: '#979ea1',
        width: 4,
    }),
});

const interStyle = new Style({
    stroke: new Stroke({
        color: '#02e7f7',
        width: 4,
    }),
});

const interFinishedStyle = new Style({
    stroke: new Stroke({
        color: '#02a9f7',
        width: 4,
    }),
});
  
const flightsSource = new Vector({
    loader: function () {
        for (let i = 0; i < flightsData.length; i++) {
            const flight = flightsData[i];
            const ori = flight.ori;
            const des = flight.des;
            const inter = flight.inter || [];
            
            let allPoints = [ori, ...inter, des];
            let allGeometries = [];

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

                // Add arc coordinates to the concatenated points array
                allGeometries = allGeometries.concat(arcLine.geometries);
            }

            // paths which cross the -180°/+180° meridian are split
            // into two sections which will be animated sequentially
            const features = [];
            allGeometries.map(function (geometry, i) {
                const line = new LineString(geometry.coords);
                line.transform('EPSG:4326', 'EPSG:3857');
                const isInter = (i !== 0 && i !== allGeometries.length - 1);

                features.push(
                new Feature({
                    geometry: line,
                    finished: false,
                    segmentCount: allGeometries.length,
                    isInter: isInter, 
                }),
                );
            });
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
            if (feature.get('isInter')) {
                return interFinishedStyle;
            } else {
                return oriDesFinishedStyle;
            }
        }
        return null;
    },
});
  
map.addLayer(flightsLayer);
  
const pointsPerMs = 0.02;
function animateFlights(event) {
    const vectorContext = getVectorContext(event);
    const frameState = event.frameState;
    const features = flightsSource.getFeatures();

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
            flightsSource.addFeature(feature);
            const segmentSum = feature.getGeometry().getCoordinates().length - 1;
            const duration = segmentSum / (pointsPerMs * features.length);
            start += duration;
        });
    }, timeout);
}
