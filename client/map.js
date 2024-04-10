import './map.css';
import TileLayer from 'ol/layer/Tile';
import {Map, View} from 'ol';
import StadiaMaps from 'ol/source/StadiaMaps.js';

const tileLayer = new TileLayer({
    source: new StadiaMaps({
        layer: 'alidade_satellite',
    }),
});

const view = new View({
    center: [-8800000, 5400000],
    zoom: 7
});


const map = new Map({
  target: 'map-container',
  layers: [tileLayer],
  view: view
});
