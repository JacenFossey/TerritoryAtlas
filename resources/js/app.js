import { AttributionControl, Map, NavigationControl, setWorkerUrl } from 'maplibre-gl';
import mapWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', {
            scope: '/',
            updateViaCache: 'none',
        }).catch((error) => {
            console.error('Service worker registration failed:', error);
        });
    });
}

const mapLegend = document.querySelector('[data-map-legend]');

if (mapLegend) {
    const mobileLegendQuery = window.matchMedia('(max-width: 48rem)');
    const syncLegendDisclosure = ({ matches }) => mapLegend.toggleAttribute('open', !matches);

    syncLegendDisclosure(mobileLegendQuery);
    mobileLegendQuery.addEventListener('change', syncLegendDisclosure);
}

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

    const loadingState = mapContainer.querySelector('[data-map-loading]');
    const loadingMessage = mapContainer.querySelector('[data-map-loading-message]');
    const mapRetry = mapContainer.querySelector('[data-map-retry]');
    const selectedRegionMessage = document.querySelector('[data-selected-region]');
    const regionSelect = document.querySelector('[data-region-select]');
    const regionSelectStatus = document.querySelector('[data-region-select-status]');
    const layerToggles = document.querySelectorAll('[data-layer-toggle]');
    const areaDetails = document.querySelector('[data-area-details]');
    const areaDetailsName = document.querySelector('[data-area-name]');
    const areaDetailsType = document.querySelector('[data-area-type]');
    const areaDetailsStatus = document.querySelector('[data-area-status]');
    const areaDetailsPrecision = document.querySelector('[data-area-precision]');
    const areaDetailsSource = document.querySelector('[data-area-source]');
    const areaDetailsFeedback = document.querySelector('[data-area-feedback]');
    const areaDetailsContent = document.querySelector('[data-area-content]');
    const areaMemberships = document.querySelector('[data-area-memberships]');
    const areaHierarchy = document.querySelector('[data-area-hierarchy]');
    const areaChildrenSection = document.querySelector('[data-area-children-section]');
    const areaChildren = document.querySelector('[data-area-children]');
    const areaNearbySection = document.querySelector('[data-area-nearby-section]');
    const areaNearby = document.querySelector('[data-area-nearby]');
    const areaDetailsClose = document.querySelector('[data-area-details-close]');
    const areaSearch = document.querySelector('[data-area-search]');
    const areaSearchInput = document.querySelector('[data-area-search-input]');
    const areaSearchResults = document.querySelector('[data-area-search-results]');
    const areaSearchFeedback = document.querySelector('[data-area-search-feedback]');

    let mapReady = false;

    const showMapError = (message) => {
        if (loadingMessage) {
            loadingMessage.textContent = message;
        }

        if (mapRetry) {
            mapRetry.hidden = false;
        }
    };

    mapRetry?.addEventListener('click', () => window.location.reload());

    map.on('error', ({ error }) => {
        console.error('Map failed to load:', error?.message ?? error);

        if (!mapReady) {
            showMapError('Unable to load the map. Check your connection and try again.');
        }
    });

    map.once('load', async () => {
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
        const commonSourceId = 'common-places';
        const commonLayerIds = [
            'common-places-points',
            'common-places-labels',
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
        let areaSearchRequest = null;
        let areaSearchTimer = null;
        let searchResults = [];
        let activeSearchResultIndex = -1;
        let selectionReturnTarget = null;

        if (loadingMessage) {
            loadingMessage.textContent = 'Loading geography…';
        }

        const fetchBoundaryCollection = (url) => fetch(url).then((response) => {
            if (!response.ok) {
                throw new Error(`Boundary request failed with status ${response.status}`);
            }

            return response.json();
        });
        const boundaryCollections = new globalThis.Map();
        const boundaryUrls = new globalThis.Map([
            [sourceId, mapConfig.majorBoundariesUrl],
            [municipalSourceId, mapConfig.lowerBoundariesUrl],
            [commonSourceId, mapConfig.commonPlacesUrl],
        ]);
        const loadBoundaryCollection = (source) => {
            if (!boundaryCollections.has(source)) {
                boundaryCollections.set(source, fetchBoundaryCollection(boundaryUrls.get(source)));
            }

            return boundaryCollections.get(source);
        };
        let majorBoundaryCollection;
        let municipalBoundaryCollection;
        let commonPlaceCollection;

        try {
            [majorBoundaryCollection, municipalBoundaryCollection, commonPlaceCollection] = await Promise.all([
                loadBoundaryCollection(sourceId),
                loadBoundaryCollection(municipalSourceId),
                loadBoundaryCollection(commonSourceId),
            ]);
        } catch (error) {
            console.error('Geography failed to load:', error?.message ?? error);
            showMapError('Unable to load map geography. Check your connection and try again.');

            return;
        }
        const commonAreaTypes = new Set([
            'community',
            'neighbourhood',
            'historic_area',
            'business_district',
        ]);

        const sourceForAreaType = (areaType) => {
            if (areaType === 'lower_tier') {
                return municipalSourceId;
            }

            return commonAreaTypes.has(areaType) ? commonSourceId : sourceId;
        };

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
                    returnFocusTo: button,
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
            areaDetailsPrecision.textContent = area.boundary_precision === 'point_only'
                ? 'Common geography shown as a representative point; no boundary is asserted.'
                : area.boundary_precision === 'approximate'
                    ? 'Approximate common-geography boundary.'
                    : '';
            areaDetailsSource.textContent = area.source_name ? `Source: ${area.source_name}` : '';

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
            replaceList(areaNearby, area.nearby ?? []);
            areaNearbySection.hidden = (area.nearby ?? []).length === 0;
            areaDetailsFeedback.textContent = '';
            areaDetailsContent.hidden = false;
            areaDetailsName.focus({ preventScroll: true });
        };

        const selectArea = ({ source, featureId, geometryKey, name, returnFocusTo = null }) => {
            clearSelection();
            selectionReturnTarget = returnFocusTo ?? map.getCanvas();
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

        const geometryBounds = (geometry) => {
            let west = Infinity;
            let south = Infinity;
            let east = -Infinity;
            let north = -Infinity;

            const includeCoordinates = (coordinates) => {
                if (typeof coordinates[0] === 'number') {
                    west = Math.min(west, coordinates[0]);
                    south = Math.min(south, coordinates[1]);
                    east = Math.max(east, coordinates[0]);
                    north = Math.max(north, coordinates[1]);

                    return;
                }

                for (const childCoordinates of coordinates) {
                    includeCoordinates(childCoordinates);
                }
            };

            includeCoordinates(geometry.coordinates);

            return [[west, south], [east, north]];
        };

        const focusArea = (source, geometryKey) => {
            loadBoundaryCollection(source)
                .then(({ features }) => {
                    const feature = features.find(({ properties }) => properties.id === geometryKey);

                    if (!feature) {
                        return;
                    }

                    if (feature.geometry.type === 'Point') {
                        map.easeTo({
                            center: feature.geometry.coordinates,
                            zoom: 11,
                            duration: 700,
                        });

                        return;
                    }

                    map.fitBounds(geometryBounds(feature.geometry), {
                        padding: 48,
                        maxZoom: 10,
                        duration: 700,
                    });
                })
                .catch((error) => {
                    console.error('Unable to focus searched area:', error?.message ?? error);
                });
        };

        const setActiveSearchResult = (index) => {
            const options = areaSearchResults?.querySelectorAll('[role="option"]') ?? [];
            activeSearchResultIndex = index;

            for (const [optionIndex, option] of options.entries()) {
                const isActive = optionIndex === index;
                option.setAttribute('aria-selected', isActive ? 'true' : 'false');
            }

            const activeOption = options[index];

            if (activeOption) {
                areaSearchInput?.setAttribute('aria-activedescendant', activeOption.id);
                activeOption.scrollIntoView({ block: 'nearest' });
            } else {
                areaSearchInput?.removeAttribute('aria-activedescendant');
            }
        };

        const closeSearchResults = () => {
            window.clearTimeout(areaSearchTimer);
            areaSearchTimer = null;
            areaSearchRequest?.abort();
            areaSearchRequest = null;
            searchResults = [];
            activeSearchResultIndex = -1;
            areaSearchResults?.replaceChildren();

            if (areaSearchResults) {
                areaSearchResults.hidden = true;
            }

            areaSearchInput?.setAttribute('aria-expanded', 'false');
            areaSearchInput?.removeAttribute('aria-activedescendant');
        };

        const chooseSearchResult = (area) => {
            const source = sourceForAreaType(area.area_type);
            areaSearchInput.value = area.name;
            closeSearchResults();
            areaSearchFeedback.textContent = `${area.name} selected`;
            selectArea({
                source,
                featureId: area.geometry_key,
                geometryKey: area.geometry_key,
                name: area.name,
                returnFocusTo: areaSearchInput,
            });
            focusArea(source, area.geometry_key);
        };

        const renderSearchResults = (areas) => {
            searchResults = areas;
            activeSearchResultIndex = -1;
            const options = areas.map((area, index) => {
                const option = document.createElement('li');
                option.id = `area-search-option-${index}`;
                option.className = 'area-search-option';
                option.role = 'option';
                option.setAttribute('aria-selected', 'false');

                const name = document.createElement('strong');
                name.textContent = area.name;
                const subtitle = document.createElement('span');
                subtitle.textContent = area.subtitle;
                option.append(name, subtitle);
                option.addEventListener('click', () => chooseSearchResult(area));
                option.addEventListener('pointermove', () => setActiveSearchResult(index));

                return option;
            });

            areaSearchResults.replaceChildren(...options);
            areaSearchResults.hidden = areas.length === 0;
            areaSearchInput.setAttribute('aria-expanded', areas.length > 0 ? 'true' : 'false');
            areaSearchFeedback.textContent = areas.length === 0
                ? 'No matching places found.'
                : `${areas.length} ${areas.length === 1 ? 'place' : 'places'} found.`;
        };

        const searchAreas = (query) => {
            areaSearchRequest?.abort();
            const request = new AbortController();
            areaSearchRequest = request;
            const url = new URL(mapConfig.areaSearchUrl, window.location.href);
            url.searchParams.set('q', query);
            areaSearchFeedback.textContent = 'Searching…';

            fetch(url, {
                headers: { Accept: 'application/json' },
                signal: request.signal,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Area search failed with status ${response.status}`);
                    }

                    return response.json();
                })
                .then(({ data }) => {
                    if (areaSearchRequest === request) {
                        renderSearchResults(data);
                    }
                })
                .catch((error) => {
                    if (error.name === 'AbortError' || areaSearchRequest !== request) {
                        return;
                    }

                    console.error('Area search failed:', error?.message ?? error);
                    closeSearchResults();
                    areaSearchFeedback.textContent = 'Search is unavailable.';
                });
        };

        areaSearchInput?.addEventListener('input', () => {
            window.clearTimeout(areaSearchTimer);
            areaSearchRequest?.abort();
            const query = areaSearchInput.value.trim();

            if (!query) {
                closeSearchResults();
                areaSearchFeedback.textContent = '';

                return;
            }

            areaSearchTimer = window.setTimeout(() => searchAreas(query), 180);
        });

        areaSearchInput?.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' && searchResults.length > 0) {
                event.preventDefault();
                setActiveSearchResult((activeSearchResultIndex + 1) % searchResults.length);
            } else if (event.key === 'ArrowUp' && searchResults.length > 0) {
                event.preventDefault();
                setActiveSearchResult(
                    activeSearchResultIndex <= 0 ? searchResults.length - 1 : activeSearchResultIndex - 1,
                );
            } else if (event.key === 'Enter' && activeSearchResultIndex >= 0) {
                event.preventDefault();
                chooseSearchResult(searchResults[activeSearchResultIndex]);
            } else if (event.key === 'Escape' && !areaSearchResults?.hidden) {
                event.stopPropagation();
                closeSearchResults();
            }
        });

        document.addEventListener('pointerdown', (event) => {
            if (areaSearch && !areaSearch.contains(event.target)) {
                closeSearchResults();
            }
        });

        const dismissAreaDetails = () => {
            areaDetailsRequest?.abort();
            areaDetailsRequest = null;
            clearSelection();
            areaDetails.hidden = true;
            mapWorkspace?.classList.remove('details-open');

            if (regionSelect) {
                regionSelect.value = '';
            }

            if (selectionReturnTarget?.isConnected && !selectionReturnTarget.disabled) {
                selectionReturnTarget.focus({ preventScroll: true });
            }

            selectionReturnTarget = null;

            if (selectedRegionMessage) {
                selectedRegionMessage.textContent = 'Area selection dismissed';
            }
        };

        map.addSource(commonSourceId, {
            type: 'geojson',
            data: commonPlaceCollection,
            promoteId: 'id',
        });

        map.addLayer(
            {
                id: commonLayerIds[0],
                type: 'circle',
                source: commonSourceId,
                minzoom: 8.5,
                paint: {
                    'circle-color': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false],
                        '#d9772f',
                        '#175f50',
                    ],
                    'circle-radius': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false],
                        7,
                        4.5,
                    ],
                    'circle-stroke-color': '#f7f6f2',
                    'circle-stroke-width': 1.5,
                },
            },
            firstBasemapLabelId,
        );

        map.addLayer({
            id: commonLayerIds[1],
            type: 'symbol',
            source: commonSourceId,
            minzoom: 9.25,
            layout: {
                'text-field': ['get', 'name'],
                'text-font': ['Noto Sans Regular'],
                'text-size': ['interpolate', ['linear'], ['zoom'], 9.25, 11.5, 12, 14],
                'text-offset': [0, 1],
                'text-anchor': 'top',
                'text-padding': 2,
            },
            paint: {
                'text-color': '#173c2b',
                'text-halo-color': 'rgba(247, 246, 242, 0.94)',
                'text-halo-width': 1.25,
            },
        });

        map.addSource(municipalSourceId, {
            type: 'geojson',
            data: municipalBoundaryCollection,
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
            data: majorBoundaryCollection,
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
        map.moveLayer(commonLayerIds[0], labelLayerId);
        map.moveLayer(commonLayerIds[1], labelLayerId);

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
            const layerIds = {
                major: [fillLayerId, outlineLayerId, labelLayerId],
                municipal: municipalLayerIds,
                common: commonLayerIds,
            }[toggle.dataset.layerToggle] ?? [];
            const syncLayerVisibility = () => {
                setLayerVisibility(layerIds, toggle.checked);
            };

            syncLayerVisibility();
            toggle.addEventListener('change', syncLayerVisibility);
        }

        if (regionSelect) {
            loadBoundaryCollection(sourceId)
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
                    returnFocusTo: regionSelect,
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

        map.on('mouseenter', commonLayerIds[0], () => {
            map.getCanvas().style.cursor = 'pointer';
        });

        map.on('mouseleave', commonLayerIds[0], () => {
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
                layers: [commonLayerIds[0], municipalLayerIds[0], fillLayerId],
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

        mapReady = true;
        loadingState?.remove();
    });
}
