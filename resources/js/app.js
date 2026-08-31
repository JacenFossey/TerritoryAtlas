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
    const selectedRegionMessage = document.querySelector('[data-selected-region]');
    const regionSelect = document.querySelector('[data-region-select]');

    map.on('error', ({ error }) => {
        console.error('Map failed to load:', error?.message ?? error);

        if (loadingMessage) {
            loadingMessage.textContent = 'Unable to load the map.';
        }
    });

    map.once('load', () => {
        const sourceId = 'major-boundaries';
        const fillLayerId = 'major-boundaries-fill';
        const outlineLayerId = 'major-boundaries-outline';
        const labelLayerId = 'major-boundaries-label';
        const firstBasemapLabelId = map.getStyle().layers.find(({ type }) => type === 'symbol')?.id;
        let hoveredRegionId = null;
        let selectedRegionId = null;

        const selectRegion = (featureId, regionId, regionName) => {
            if (selectedRegionId !== null) {
                map.setFeatureState({ source: sourceId, id: selectedRegionId }, { selected: false });
            }

            selectedRegionId = featureId;
            map.setFeatureState({ source: sourceId, id: selectedRegionId }, { selected: true });

            if (regionSelect && regionSelect.value !== regionId) {
                regionSelect.value = regionId;
            }

            if (selectedRegionMessage) {
                selectedRegionMessage.textContent = `${regionName} selected`;
            }
        };

        map.addSource(sourceId, {
            type: 'geojson',
            data: mapConfig.majorBoundariesUrl,
            promoteId: 'id',
        });

        map.addLayer(
            {
                id: fillLayerId,
                type: 'fill',
                source: sourceId,
                paint: {
                    'fill-color': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false],
                        '#d9772f',
                        ['match', ['get', 'area_type'], 'single_tier', '#4f8c78', '#6c83a4'],
                    ],
                    'fill-opacity': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false],
                        0.5,
                        ['boolean', ['feature-state', 'hover'], false],
                        0.32,
                        0.18,
                    ],
                },
            },
            firstBasemapLabelId,
        );

        map.addLayer(
            {
                id: outlineLayerId,
                type: 'line',
                source: sourceId,
                paint: {
                    'line-color': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false],
                        '#8f3f18',
                        '#304d52',
                    ],
                    'line-opacity': 0.9,
                    'line-width': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false],
                        3,
                        ['boolean', ['feature-state', 'hover'], false],
                        2,
                        1.15,
                    ],
                },
            },
            firstBasemapLabelId,
        );

        map.addLayer({
            id: labelLayerId,
            type: 'symbol',
            source: sourceId,
            minzoom: 5.25,
            layout: {
                'text-field': ['get', 'name'],
                'text-font': ['Noto Sans Regular'],
                'text-size': ['interpolate', ['linear'], ['zoom'], 5.25, 10, 8, 13],
                'text-max-width': 8,
                'text-padding': 3,
            },
            paint: {
                'text-color': '#173c2b',
                'text-halo-color': 'rgba(247, 246, 242, 0.94)',
                'text-halo-width': 1.5,
            },
        });

        if (regionSelect) {
            fetch(mapConfig.majorBoundariesUrl)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Boundary request failed with status ${response.status}`);
                    }

                    return response.json();
                })
                .then(({ features }) => {
                    const regions = features
                        .map(({ id, properties }) => ({ id, name: properties.name }))
                        .sort((firstRegion, secondRegion) => firstRegion.name.localeCompare(secondRegion.name));

                    for (const region of regions) {
                        const option = document.createElement('option');
                        option.value = region.id;
                        option.textContent = region.name;
                        regionSelect.append(option);
                    }

                    regionSelect.disabled = false;
                })
                .catch((error) => {
                    console.error('Region selector failed to load:', error?.message ?? error);
                });

            regionSelect.addEventListener('change', () => {
                if (!regionSelect.value) {
                    return;
                }

                selectRegion(regionSelect.value, regionSelect.value, regionSelect.selectedOptions[0].textContent);
            });
        }

        map.on('mousemove', fillLayerId, ({ features }) => {
            const region = features?.[0];

            if (!region || region.id === hoveredRegionId) {
                return;
            }

            if (hoveredRegionId !== null) {
                map.setFeatureState({ source: sourceId, id: hoveredRegionId }, { hover: false });
            }

            hoveredRegionId = region.id;
            map.setFeatureState({ source: sourceId, id: hoveredRegionId }, { hover: true });
            map.getCanvas().style.cursor = 'pointer';
        });

        map.on('mouseleave', fillLayerId, () => {
            if (hoveredRegionId !== null) {
                map.setFeatureState({ source: sourceId, id: hoveredRegionId }, { hover: false });
            }

            hoveredRegionId = null;
            map.getCanvas().style.cursor = '';
        });

        map.on('click', fillLayerId, ({ features }) => {
            const region = features?.[0];

            if (!region) {
                return;
            }

            selectRegion(region.id, region.properties.id, region.properties.name);
        });

        loadingMessage?.remove();
    });
}
