import './map.css';
import TileLayer from 'ol/layer/Tile';
import {Map, View} from 'ol';
import StadiaMaps from 'ol/source/StadiaMaps.js';

import Heatmap from 'ol/layer/Heatmap';
import Vector from 'ol/source/Vector';
import Feature from 'ol/Feature';
import Point from 'ol/geom/Point';
import { fromLonLat } from 'ol/proj';

const tileLayer = new TileLayer({
    source: new StadiaMaps({
        layer: 'alidade_satellite',
    }),
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

const view = new View({
    center: [-8800000, 5400000],
    zoom: 7
});

const map = new Map({
  target: 'map-container',
  layers: [tileLayer, heatmapLayer],
  view: view
});
