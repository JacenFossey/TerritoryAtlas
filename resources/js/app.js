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
    const layerToggles = document.querySelectorAll('[data-layer-toggle]');

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
        const municipalSourceId = 'lower-boundaries';
        const municipalLayerIds = [
            'lower-boundaries-fill',
            'lower-boundaries-outline',
            'lower-boundaries-label',
        ];
        const basemapLayers = map.getStyle().layers;
        const firstBasemapLabelId = basemapLayers.find(({ type }) => type === 'symbol')?.id;
        const basemapPlaceLabelIds = basemapLayers
            .filter((layer) => layer.type === 'symbol' && layer['source-layer'] === 'place')
            .map(({ id }) => id);
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

        map.addSource(municipalSourceId, {
            type: 'geojson',
            data: mapConfig.lowerBoundariesUrl,
            promoteId: 'id',
        });

        map.addLayer(
            {
                id: municipalLayerIds[0],
                type: 'fill',
                source: municipalSourceId,
                minzoom: 7.25,
                paint: {
                    'fill-color': [
                        'match',
                        ['%', ['to-number', ['get', 'source_identifier']], 5],
                        0,
                        '#e8b86d',
                        1,
                        '#79b8a4',
                        2,
                        '#8fa8cf',
                        3,
                        '#d99a8d',
                        '#b5a1c9',
                    ],
                    'fill-opacity': 0.2,
                },
            },
            firstBasemapLabelId,
        );

        map.addLayer(
            {
                id: municipalLayerIds[1],
                type: 'line',
                source: municipalSourceId,
                minzoom: 7.25,
                paint: {
                    'line-color': '#385e61',
                    'line-opacity': 0.82,
                    'line-width': ['interpolate', ['linear'], ['zoom'], 7.25, 0.8, 10, 1.4],
                },
            },
            firstBasemapLabelId,
        );

        map.addLayer(
            {
                id: municipalLayerIds[2],
                type: 'symbol',
                source: municipalSourceId,
                minzoom: 8,
                layout: {
                    'text-field': ['get', 'name'],
                    'text-font': ['Noto Sans Regular'],
                    'text-size': ['interpolate', ['linear'], ['zoom'], 8, 11.7, 11, 15.6],
                    'text-max-width': 7,
                    'text-padding': 2,
                },
                paint: {
                    'text-color': '#405e61',
                    'text-halo-color': 'rgba(247, 246, 242, 0.9)',
                    'text-halo-width': 1.25,
                },
            },
            firstBasemapLabelId,
        );

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
                'text-size': ['interpolate', ['linear'], ['zoom'], 5.25, 13, 8, 16.9],
                'text-max-width': 8,
                'text-padding': 3,
            },
            paint: {
                'text-color': '#173c2b',
                'text-halo-color': 'rgba(247, 246, 242, 0.94)',
                'text-halo-width': 1.5,
            },
        });

        map.moveLayer(municipalLayerIds[0], outlineLayerId);
        map.moveLayer(municipalLayerIds[1], outlineLayerId);
        map.moveLayer(municipalLayerIds[2], labelLayerId);

        for (const layerId of basemapPlaceLabelIds) {
            const textSize = map.getLayoutProperty(layerId, 'text-size');

            if (typeof textSize === 'number') {
                map.setLayoutProperty(layerId, 'text-size', textSize * 0.7);
            } else if (Array.isArray(textSize) && textSize[0] === 'interpolate') {
                const smallerTextSize = textSize.map((value, index) => (
                    index >= 4 && index % 2 === 0 && typeof value === 'number' ? value * 0.7 : value
                ));

                map.setLayoutProperty(layerId, 'text-size', smallerTextSize);
            }
        }

        const setLayerVisibility = (layerIds, visible) => {
            for (const layerId of layerIds) {
                map.setLayoutProperty(layerId, 'visibility', visible ? 'visible' : 'none');
            }
        };

        for (const toggle of layerToggles) {
            toggle.addEventListener('change', () => {
                const isMajorToggle = toggle.dataset.layerToggle === 'major';

                setLayerVisibility(
                    isMajorToggle ? [fillLayerId, outlineLayerId, labelLayerId] : municipalLayerIds,
                    toggle.checked,
                );
            });
        }

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
