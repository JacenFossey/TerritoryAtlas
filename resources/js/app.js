import { AttributionControl, Map, NavigationControl, setWorkerUrl } from 'maplibre-gl';
import mapWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';

const mapContainer = document.querySelector('#map');
const mapConfigElement = document.querySelector('#map-config');

if (mapContainer && mapConfigElement) {
    const mapConfig = JSON.parse(mapConfigElement.textContent);

    setWorkerUrl(mapWorkerUrl);

    const map = new Map({
        container: mapContainer,
        style: mapConfig.styleUrl,
        bounds: mapConfig.bounds,
        fitBoundsOptions: {
            padding: 36,
        },
        attributionControl: false,
    });

    map.addControl(
        new NavigationControl({
            showCompass: false,
        }),
        'top-right',
    );

    map.addControl(
        new AttributionControl({
            compact: true,
            customAttribution: mapConfig.attribution,
        }),
    );

    const loadingMessage = mapContainer.querySelector('[data-map-loading]');

    map.on('error', ({ error }) => {
        console.error('Map failed to load:', error?.message ?? error);

        if (loadingMessage) {
            loadingMessage.textContent = 'Unable to load the map.';
        }
    });

    map.once('load', () => {
        loadingMessage?.remove();
    });
}
