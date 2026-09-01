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
    const regionSelectStatus = document.querySelector('[data-region-select-status]');
    const layerToggles = document.querySelectorAll('[data-layer-toggle]');
    const areaDetails = document.querySelector('[data-area-details]');
    const areaDetailsName = document.querySelector('[data-area-name]');
    const areaDetailsType = document.querySelector('[data-area-type]');
    const areaDetailsStatus = document.querySelector('[data-area-status]');
    const areaDetailsFeedback = document.querySelector('[data-area-feedback]');
    const areaDetailsContent = document.querySelector('[data-area-content]');
    const areaMemberships = document.querySelector('[data-area-memberships]');
    const areaHierarchy = document.querySelector('[data-area-hierarchy]');
    const areaChildrenSection = document.querySelector('[data-area-children-section]');
    const areaChildren = document.querySelector('[data-area-children]');
    const areaDetailsClose = document.querySelector('[data-area-details-close]');

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
        const mapWorkspace = document.querySelector('.map-workspace');
        let hoveredRegionId = null;
        let selectedFeature = null;
        let areaDetailsRequest = null;

        const sourceForAreaType = (areaType) => (
            areaType === 'lower_tier' ? municipalSourceId : sourceId
        );

        const formatAreaType = (areaType) => {
            const labels = {
                ggh: 'Regional context',
                upper_tier: 'Upper-tier municipality',
                single_tier: 'Single-tier municipality',
                lower_tier: 'Lower-tier municipality',
            };

            return labels[areaType] ?? areaType.replaceAll('_', ' ');
        };

        const clearSelection = () => {
            if (selectedFeature !== null) {
                map.setFeatureState(selectedFeature, { selected: false });
            }

            selectedFeature = null;
        };

        const createAreaListItem = (area) => {
            const item = document.createElement('li');

            if (!area.geometry_key) {
                item.textContent = area.name;

                return item;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'area-details-link';
            button.textContent = area.name;
            button.addEventListener('click', () => {
                selectArea({
                    source: sourceForAreaType(area.area_type),
                    featureId: area.geometry_key,
                    geometryKey: area.geometry_key,
                    name: area.name,
                });
            });
            item.append(button);

            return item;
        };

        const replaceList = (list, areas) => {
            list.replaceChildren(...areas.map(createAreaListItem));
        };

        const renderAreaDetails = (area) => {
            areaDetailsName.textContent = area.name;
            areaDetailsType.textContent = formatAreaType(area.area_type);
            areaDetailsStatus.textContent = area.administrative_status ?? '';

            const memberships = [];

            if (area.is_ggh) {
                memberships.push('Greater Golden Horseshoe');
            }

            if (area.is_gta) {
                memberships.push('Greater Toronto Area');
            }

            replaceList(
                areaMemberships,
                memberships.map((name) => ({ name, geometry_key: null })),
            );
            replaceList(areaHierarchy, area.hierarchy);
            replaceList(areaChildren, area.children);
            areaChildrenSection.hidden = area.children.length === 0;
            areaDetailsFeedback.textContent = '';
            areaDetailsContent.hidden = false;
            areaDetailsName.focus({ preventScroll: true });
        };

        const selectArea = ({ source, featureId, geometryKey, name }) => {
            clearSelection();
            selectedFeature = { source, id: featureId };
            map.setFeatureState(selectedFeature, { selected: true });

            if (regionSelect) {
                regionSelect.value = source === sourceId ? geometryKey : '';
            }

            if (selectedRegionMessage) {
                selectedRegionMessage.textContent = `${name} selected`;
            }

            areaDetailsRequest?.abort();
            const request = new AbortController();
            areaDetailsRequest = request;
            areaDetails.hidden = false;
            mapWorkspace?.classList.add('details-open');
            areaDetailsName.textContent = name;
            areaDetailsType.textContent = '';
            areaDetailsFeedback.textContent = `Loading details for ${name}…`;
            areaDetailsContent.hidden = true;

            const detailsUrl = mapConfig.areaDetailsUrl.replace(
                '__GEOMETRY_KEY__',
                encodeURIComponent(geometryKey),
            );

            fetch(detailsUrl, {
                headers: {
                    Accept: 'application/json',
                },
                signal: request.signal,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Area details request failed with status ${response.status}`);
                    }

                    return response.json();
                })
                .then(({ data }) => {
                    if (areaDetailsRequest !== request) {
                        return;
                    }

                    renderAreaDetails(data);
                })
                .catch((error) => {
                    if (error.name === 'AbortError' || areaDetailsRequest !== request) {
                        return;
                    }

                    console.error('Area details failed to load:', error?.message ?? error);
                    areaDetailsFeedback.textContent = 'Unable to load details for this area.';
                });
        };

        const dismissAreaDetails = () => {
            areaDetailsRequest?.abort();
            areaDetailsRequest = null;
            clearSelection();
            areaDetails.hidden = true;
            mapWorkspace?.classList.remove('details-open');

            if (regionSelect) {
                regionSelect.value = '';
                regionSelect.focus({ preventScroll: true });
            }

            if (selectedRegionMessage) {
                selectedRegionMessage.textContent = 'Area selection dismissed';
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
                        'case',
                        ['boolean', ['feature-state', 'selected'], false],
                        '#d9772f',
                        [
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
                    ],
                    'fill-opacity': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false],
                        0.5,
                        0.2,
                    ],
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
                    'line-color': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false],
                        '#8f3f18',
                        '#385e61',
                    ],
                    'line-opacity': 0.82,
                    'line-width': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false],
                        3,
                        ['interpolate', ['linear'], ['zoom'], 7.25, 0.8, 10, 1.4],
                    ],
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
            const isMajorToggle = toggle.dataset.layerToggle === 'major';
            const layerIds = isMajorToggle
                ? [fillLayerId, outlineLayerId, labelLayerId]
                : municipalLayerIds;
            const syncLayerVisibility = () => {
                setLayerVisibility(layerIds, toggle.checked);
            };

            syncLayerVisibility();
            toggle.addEventListener('change', syncLayerVisibility);
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
                        .map(({ properties }) => ({ id: properties.id, name: properties.name }))
                        .sort((firstRegion, secondRegion) => firstRegion.name.localeCompare(secondRegion.name));

                    for (const region of regions) {
                        const option = document.createElement('option');
                        option.value = region.id;
                        option.textContent = region.name;
                        regionSelect.append(option);
                    }

                    regionSelect.disabled = false;
                    regionSelectStatus.textContent = '';
                })
                .catch((error) => {
                    console.error('Region selector failed to load:', error?.message ?? error);
                    regionSelectStatus.textContent = 'Division selector is unavailable.';
                });

            regionSelect.addEventListener('change', () => {
                if (!regionSelect.value) {
                    return;
                }

                selectArea({
                    source: sourceId,
                    featureId: regionSelect.value,
                    geometryKey: regionSelect.value,
                    name: regionSelect.selectedOptions[0].textContent,
                });
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

        map.on('mouseenter', municipalLayerIds[0], () => {
            map.getCanvas().style.cursor = 'pointer';
        });

        map.on('mouseleave', municipalLayerIds[0], () => {
            map.getCanvas().style.cursor = '';
        });

        map.on('click', ({ point }) => {
            const area = map.queryRenderedFeatures(point, {
                layers: [municipalLayerIds[0], fillLayerId],
            })[0];

            if (!area) {
                return;
            }

            const geometryKey = area.properties.id;

            selectArea({
                source: area.source,
                featureId: geometryKey,
                geometryKey,
                name: area.properties.name,
            });
        });

        areaDetailsClose?.addEventListener('click', dismissAreaDetails);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !areaDetails.hidden) {
                dismissAreaDetails();
            }
        });

        loadingMessage?.remove();
    });
}
