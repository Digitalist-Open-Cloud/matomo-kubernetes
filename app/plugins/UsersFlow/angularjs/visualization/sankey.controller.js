(function () {
    angular.module('piwikApp').controller('UsersFlowVisualizationController', UsersFlowVisualizationController);

    UsersFlowVisualizationController.$inject = ['$scope', 'piwik', 'piwikApi', '$filter', '$rootScope'];

    // node.depth starts at 0
    // link.depth starts at 1
    function UsersFlowVisualizationController($scope, piwik, piwikApi, $filter, $rootScope) {

        var translate = $filter('translate');

        var OUT_NODE_NAME = '_out_';
        var SUMMARY_NODE_NAME = 'Others';
        var SUMMARY_NODE_NAME_TRANSLATED = translate('General_Others');

        var self = this;
        this.numSteps = 4;
        this.hasData = true;
        this.rawResponse;
        this.actionsPerStepOptions = [{key: 4, value: 4}, {key: 5, value: 5}];
        this.isLoading = false;
        this.rootElement = null;
        this.maxSankeyChartDepth = 0;
        this.maxNodeLength = 0;
        this.isExploringTraffic = false;
        this.exploreStep = false;
        this.exploreUrl = false;
        this.flowSources = [];

        piwikApi.fetch({method: 'UsersFlow.getAvailableDataSources'}).then(function (dataSources) {
            self.flowSources = [];
            angular.forEach(dataSources, function (dataSource) {
                self.flowSources.push({key: dataSource.value, value: dataSource.name});
            });
        });

        this.numActionsPerStep = parseInt($scope.actionsPerStep, 10);
        this.levelOfDetail  = parseInt($scope.levelOfDetail, 10);
        this.userFlowSource = $scope.userFlowSource;

        if (!this.numActionsPerStep) {
            this.numActionsPerStep = 5;
        }
        if (!this.levelOfDetail) {
            this.levelOfDetail = 4;
        }

        for (var i = 6; i <= 20; i = i + 2) {
            this.actionsPerStepOptions.push({key: i, value: i});
        }

        this.levelOfDetailOptions = [
            {key: 1, value: translate('UsersFlow_OptionLevelOfDetail1')},
            {key: 2, value: translate('UsersFlow_OptionLevelOfDetail2')},
            {key: 3, value: translate('UsersFlow_OptionLevelOfDetail3')},
            {key: 4, value: translate('UsersFlow_OptionLevelOfDetail4')},
            {key: 5, value: translate('UsersFlow_OptionLevelOfDetail5')},
            {key: 6, value: translate('UsersFlow_OptionLevelOfDetail6')},
        ];

        this.setRootElement = function (rootElement) {
            // a controller should not handle elements but in this case we cannot do it via angular template
            // directly
            this.rootElement = rootElement;
        };

        function isUrlLike(name)
        {
            if (!name) {
                return false;
            }
            if (self.userFlowSource !== 'page_url') {
                return false;
            }

            var urlPattern = new RegExp('^(.+)[.](.+)\/(.*)$');
            return urlPattern.test(name);
        }

        function completeUrl(name)
        {
            if (name.indexOf('http') === 0) {
                return name;
            }
            // piwik stores urls without eg http://www.
            return location.protocol + '//' + name;
        }

        function canEnableExploreTraffic()
        {
            return piwik.period !== 'year';
        }

        function some(array, predicate) {
            if (!array) {
                return false;
            }
            for (var index = 0; index < array.length; index++) {
                if (predicate(array[index], index, array)) {
                    return true
                }
            }
            return false;
        }

        function showGroupDetails(rowLabel, depth, onlyOthers, idSubtable)
        {
            var url = 'showtitle=1&widget=1&module=UsersFlow&action=getInteractionActions&interactionPosition=' + encodeURIComponent(depth);
            if (onlyOthers) {
                url += '&offsetActionsPerStep=' + encodeURIComponent(self.numActionsPerStep);
            }
            if (rowLabel) {
                url += '&rowLabel=' + encodeURIComponent(rowLabel);
            }
            if (idSubtable) {
                url += '&idSubtable=' + encodeURIComponent(idSubtable);
            }
            if (self.userFlowSource) {
                url += '&dataSource=' + encodeURIComponent(self.userFlowSource);
            }
            Piwik_Popover.createPopupAndLoadUrl(url, translate('UsersFlow_Interactions'));
        }

        function setSankeyStep(setStep)
        {
            if (setStep > self.maxSankeyChartDepth) {
                self.numSteps = 1;
            } else if (setStep < 1) {
                self.numSteps = 1;
            } else {
                self.numSteps = setStep;
            }

            clearSankeyCahrt();

            var nodesAndLinks = buildNodesAndIndexes(self.rawResponse);
            drawSankeyChart(nodesAndLinks);
        }

        function addSankeyStep(){
            var step = self.numSteps;
            step++;
            setSankeyStep(step);
        }

        function getSankeyNode()
        {
            var node = angular.element('.sankeyChart', self.rootElement);
            if (node && node.length && node[0]) {
                return node[0];
            }
            return node;
        }

        function clearSankeyCahrt() {
            var node = getSankeyNode();
            if (node) {
                var svg = d3.select(node).selectAll('svg');
                if (svg) {
                    d3.select(node).selectAll('svg').remove();
                }
            }
        }

        function makeToolTip(message)
        {
            return '<span class="userFlowNodeTooltip">' + message + '</span>';
        }

        function isOutNode(name)
        {
            return name && name === OUT_NODE_NAME;
        }

        function isSummaryNode(name)
        {
            return name && (name === SUMMARY_NODE_NAME || name === SUMMARY_NODE_NAME_TRANSLATED);
        }

        function isSearchNode(name)
        {
            return name && (name === SEARCH_NODE_NAME || name === SEARCH_NODE_NAME_TRANSLATED);
        }

        function setMaxSankeyChartDepth(maxDepth) {
            self.maxSankeyChartDepth = parseInt(maxDepth, 10);
        }

        function setMaxNodeLength(maxLength) {
            self.maxNodeLength = parseInt(maxLength, 10);
        }

        function getPercentage(val1, val2) {
            var percentage = Math.round((val1 / val2 * 100) * 100) / 100;

            return percentage + "%";
        }

        function drawSankeyChart(sankeyDataSet) {

            var NODE_WIDTH = 200;
            var NODE_PADDING = 40;
            var DEPTH_WIDTH = 350;

            var margin = {top: 70, right: 20, bottom: 20, left: 5};
            var width = 550 + ((self.numSteps - 2) * DEPTH_WIDTH) + 150;
            var sankeyWidth = width - 150;  //for next button
            var height = self.maxNodeLength * 100 + margin.top;

            var sankeyNode = getSankeyNode();

            $(sankeyNode).css('width', width + margin.left + margin.right)
                         .css('height', height + margin.top + margin.bottom + 5);

            var formatNumber = d3.format(',.0f'),
                format = function (d) {
                    return formatNumber(d);
                },
                color = d3.scaleOrdinal(d3.schemeCategory20);

            var svg = d3.select(sankeyNode).append('svg')
                .attr('width', width + margin.left + margin.right)
                .attr('height', height + margin.top + margin.bottom)
                .append('g')
                .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

            var normalGradient = svg.append('svg:defs')
                .append('svg:linearGradient')
                .attr('id', 'normalGradient')
                .attr('x1', '0%')
                .attr('y1', '0%')
                .attr('x2', '0%')
                .attr('y2', '100%')
                .attr('spreadMethod', 'pad');
            normalGradient.append('svg:stop')
                .attr('offset', '0%')
                .attr('stop-color', '#F2FFE9')
                .attr('stop-opacity', 1);
            normalGradient.append('svg:stop')
                .attr('offset', '100%')
                .attr('stop-color', '#84D04D')
                .attr('stop-opacity', 1);

            var pageOutGradient = svg.append('svg:defs')
                .append('svg:linearGradient')
                .attr('id', 'pageOutGradient')
                .attr('x1', '0%')
                .attr('y1', '0%')
                .attr('x2', '0%')
                .attr('y2', '100%')
                .attr('spreadMethod', 'pad');
            pageOutGradient.append('svg:stop')
                .attr('offset', '0%')
                .attr('stop-color', '#FCE8E8')
                .attr('stop-opacity', 1);
            pageOutGradient.append('svg:stop')
                .attr('offset', '100%')
                .attr('stop-color', '#FA5858')
                .attr('stop-opacity', 1);

            var sankey = d3.sankey()
                .nodeWidth(NODE_WIDTH)
                .nodePadding(NODE_PADDING)
                .size([sankeyWidth, height]);

            var path = sankey.link();

            if (sankeyDataSet && sankeyDataSet.nodes && sankeyDataSet.links && sankeyDataSet.depthNodes) {
                var depthInfo = svg.append('g').selectAll('.depthInfo')
                    .data(sankeyDataSet.depthNodes)
                    .enter().append('g')
                    .attr('class', function (node) {
                        return 'depthInfo depth' + (parseInt(node.depth, 10) + 1);
                    });
                depthInfo.append('rect')
                    .attr('height', 50)
                    .attr('width', NODE_WIDTH)
                    .attr('x', function (d) {
                        return d.depth * DEPTH_WIDTH;
                    })
                    .attr('y', -80)
                    .style('fill', 'none');
                var depthText = depthInfo.append('text').attr('y', -60);

                if (self.numSteps > 1) {
                    var closebuttonSvg = depthInfo.append('svg')
                        .attr('viewBox', '-100 -100 1500 1500')
                        .attr('width', '18')
                        .attr('height', '18')
                        .attr('y', '-68')
                        .attr('x', function (d) {
                            return (d.depth * DEPTH_WIDTH) + NODE_WIDTH - 10; //plus padding
                        })
                        .attr('class', 'removeStep')
                        .on('click', function (d) {
                            setSankeyStep(d.depth);
                        })
                        .style('visibility', function (d) {
                            if (d.depth > 1) {
                                return 'visible';
                            }
                            return 'hidden';
                        })
                        .attr('dy', 1);

                    closebuttonSvg.append('path')
                        .attr('d', 'M874.048 810.048c-199.936 199.936-524.16 199.936-724.096 0s-199.936-524.16 0-724.096c199.936-199.936 524.16-199.936 724.096 0s199.936 524.16 0 724.096zM692.992 267.008c-33.344-33.344-87.36-33.344-120.64 0l-60.352 60.288-60.352-60.352c-33.344-33.344-87.36-33.344-120.64 0-33.344 33.344-33.344 87.36 0 120.704l60.352 60.352-60.352 60.352c-33.344 33.344-33.344 87.36 0 120.704s87.36 33.344 120.64 0l60.352-60.352 60.352 60.352c33.344 33.344 87.36 33.344 120.64 0 33.344-33.344 33.344-87.36 0-120.704l-60.288-60.352 60.352-60.352c33.28-33.344 33.28-87.36-0.064-120.64z')
                        .attr('fill', '#999')
                        .append('title')
                        .text(translate('UsersFlow_ActionRemoveStep'));
                    closebuttonSvg.append('rect')
                        .attr('fill', 'transparent')
                        .attr('width', '900')
                        .attr('height', '900')
                        .attr('x', 50)
                        .append('title')
                        .text(translate('UsersFlow_ActionRemoveStep'));
                }

                depthText.append('svg:tspan')
                    .attr('x', function (d) {
                        return (d.depth * DEPTH_WIDTH);
                    })
                    .attr('dy', 5)
                    .attr('fill', 'black')
                    .style('font-weight', 'bold')
                    .attr('class', 'depthContainerTitle')
                    .text(function (d) {
                        return translate('UsersFlow_ColumnInteraction') + ' ' + (d.depth + 1);
                    })
                    .on('click', function (d) {
                        var depth = parseInt(d.depth, 10) + 1;
                        showGroupDetails('', depth, false);
                    })
                    .append('svg:tspan')
                    .attr('x', function (d) {
                        return (d.depth * DEPTH_WIDTH);
                    })
                    .attr('dy', 20)
                    .style('font-weight', 'normal')
                    .style('font-size', '13px')
                    .text(function (d) {
                        if ('undefined' == typeof d.totalIn) {
                            return;
                        }

                        var message = translate('General_NVisits', d.totalIn) + ', ';
                        message += translate('UsersFlow_NProceededInline', d.totalOut) + ', ';
                        message += translate('Transitions_ExitsInline', d.totalExits);

                        return message;
                    })
                    .attr('fill', 'black');

                sankey.nodes(sankeyDataSet.nodes).links(sankeyDataSet.links).layout(32);

                var tipLink = d3.tip()
                    .attr('class', 'd3-tip')
                    .offset([-10, 0])
                    .html(function (d) {
                        var bottom =  format(d.value);

                        if (d.source && d.source.totalIn) {
                            bottom += ' ('+ getPercentage(d.value, d.source.totalIn) + ')';
                        }

                        if (isOutNode(d.target.name)) {
                            var message = translate('Transitions_ExitsInline', bottom);
                            return makeToolTip(d.source.name + ": <span class='nodeTooltipExits'>" + message + "</span>");
                        }

                        var from = '"' + d.source.name + '"';
                        var to = '"' + d.target.name + '"';
                        var message = translate('UsersFlow_InteractionXToY', from, to);

                        return makeToolTip(message + "<br />" + bottom);
                    });

                var link = svg.append('g').selectAll('.link')
                    .data(sankeyDataSet.links)
                    .enter().append('path')
                    .attr('class', function (d) {
                        var className = 'link ';

                        if (isOutNode(d.target.name)) {
                            return className + ' outNodeLink';
                        }

                        var percentage = 0;
                        if (d.source.totalOut > 0) {
                            percentage = ((d.value / d.source.totalOut) * 100);
                        }
                        // we check how much it contributed in percentage
                        // to the total outgoing
                        if (percentage <= 8) {
                            className += ' linkSize1';
                        } else if (percentage <= 16) {
                            className += ' linkSize2';
                        } else if (percentage <= 24) {
                            className += ' linkSize3';
                        } else if (percentage <= 32) {
                            className += ' linkSize4';
                        } else if (percentage <= 42) {
                            className += ' linkSize5';
                        } else {
                            className += ' linkSize6';
                        }

                        return className;
                    })
                    .attr('d', path)
                    .attr('id', function (d, i) {
                        d.id = i;
                        return 'link-' + i;
                    })
                    .style('stroke', function (d) {
                        if (isOutNode(d.target.name)) {
                            return '#ec5540';
                        }
                        return '#A9E2F3';
                    })
                    .style('stroke-width', function (d) {
                        return Math.max(1, d.dy);
                    })
                    .sort(function (a, b) {
                        return b.dy - a.dy;
                    });

                link.call(tipLink)
                    .on('mouseover', tipLink.show)
                    .on('mouseout', tipLink.hide);

                /** d3-tip set */
                var tip = d3.tip()
                    .attr('class', 'd3-tip')
                    .offset([-10, 0])
                    .html(function (d) {

                        if (isOutNode(d.name)) {
                            return '';
                        }

                        return makeToolTip(d.name
                            + "<br/>" + translate('General_ColumnNbVisits') + ": <span class='nodeTooltipVisits'>" + d.totalIn + "</span>"
                            + "<br/>" + translate('UsersFlow_ColumnProceeded') + ": <span class='nodeTooltipProceeded'>" + d.totalOut + " (" + getPercentage(d.totalOut, d.totalIn) + ")</span>"
                            + "<br/>" + translate('General_ColumnExits') + ": <span class='nodeTooltipExits'>" + d.totalExits + " (" + getPercentage(d.totalExits, d.totalIn) + ")</span>"
                        );
                    });

                var node = svg.append('g').selectAll('.node')
                    .data(sankeyDataSet.nodes)
                    .enter().append('g')
                    .attr('class', function (d) {
                        var classNames = 'node nodeDepth' + (parseInt(d.depth, 10) + 1);
                        if (isOutNode(d.name)) {
                            classNames += ' outNode';
                        }
                        return classNames;
                    })
                    .attr('transform', function (d) {
                        return 'translate(' + d.x + ',' + d.y + ')';
                    })
                    .on('click', showPopup);

                node.call(tip)
                    .on('mouseover', tip.show)
                    .on('mouseout', tip.hide);

                node.append('rect')
                    .attr('height', function (d) {
                        return d.dy;
                    })
                    .attr('width', sankey.nodeWidth())
                    .style('fill', function (d) {
                        if (isOutNode(d.name)) {
                            return 'url(#pageOutGradient)';
                        }
                        return 'url(#normalGradient)';
                    })
                    .style('stroke', '#333');

                node.append('text')
                    .attr('x', 4)
                    .attr('y', -5)
                    .attr('text-anchor', 'left')
                    .attr('transform', 'rotate(0)')
                    .text(function (d) {
                        if (isOutNode(d.name)) {
                            return '';
                        }

                        var name = d.name;

                        if (isSummaryNode(name)) {
                            if (d.pagesInGroup) {
                                name += ' (>' + translate('VisitorInterest_NPages', d.pagesInGroup) + ')';
                            }

                            return name;
                        }

                        if (isUrlLike(name)) {
                            // if name is like a url, eg erer.com/... then we remove the domain
                            name = name.substr(name.indexOf('/'));
                        }

                        if (name.length > 33) {
                            return name.substr(0, 15) + '...' + name.substr(-15);
                        }

                        return name;
                    })
                    .attr('fill', 'black');

                node.append('text')
                    .attr('x', 4)
                    .attr('y', 18)
                    .attr('transform', 'rotate(0)')
                    .attr('text-anchor', 'left')
                    .text(function (i) {
                        return format(i.totalIn);
                    })
                    .attr('fill', 'black');

                function showNodeDetails(node) {
                    var depth = parseInt(node.depth, 10) + 1;

                    if (isSummaryNode(node.name)) {
                        showGroupDetails(node.name, depth, true);
                        return;
                    } else if (node.idSubtable) {
                        showGroupDetails(node.name, depth, false, node.idSubtable);
                    }
                }

                var popupExitHandlerSetup = false;

                function showPopup(node, index)
                {
                    var that = this;
                    d3.event.preventDefault();
                    d3.event.stopPropagation();

                    var isHighlighted = d3.select(this).attr('data-clicked') == '1';

                    if (!popupExitHandlerSetup) {
                        if (!$('body > .usersFlowPopupMenu').length) {
                            $('.usersFlowPopupMenu').appendTo('body');
                        }
                        popupExitHandlerSetup = true;
                        d3.select('body').on('click', function() {
                            var popupMenu = d3.select('body > .usersFlowPopupMenu');
                            popupMenu.style('display', 'none');
                            popupMenu.html('');
                        });
                    }

                    var trafficTitle = 'UsersFlow_ActionHighlightTraffic';
                    if (isHighlighted) {
                        trafficTitle = 'UsersFlow_ActionClearHighlight';
                    }

                    var popupMenu = d3.select('body > .usersFlowPopupMenu');
                    popupMenu.html('');

                    var list = popupMenu.append('ul');
                    list.append('li').attr('class', 'highlightTraffic').on('click', function() {
                        highlightNodeTraffic.apply(that, [node, index]);
                    }).text(translate(trafficTitle));

                    if (canEnableExploreTraffic() && !isSummaryNode(node.name)) {
                        list.append('li').attr('class', 'divider').html('<hr />')
                        list.append('li').attr('class', 'exploreTraffic').on('click', function() {
                            self.exploreStep = node.depth + 1;
                            self.exploreUrl = node.name;
                            self.numSteps = self.exploreStep + 2;
                            fetchData();
                        }).text(translate('UsersFlow_ExploreTraffic'));
                    }

                    if (self.isExploringTraffic) {
                        list.append('li').attr('class', 'divider').html('<hr />')
                        list.append('li').attr('class', 'unexploreTraffic').on('click', function() {
                            self.exploreStep = false;
                            self.exploreUrl = false;
                            fetchData();
                        }).text(translate('UsersFlow_UnexploreTraffic'));
                    } else {
                        if (node.idSubtable || isSummaryNode(node.name)) {
                            list.append('li').attr('class', 'divider').html('<hr />');
                            list.append('li').attr('class', 'showNodeDetails').on('click', function() {
                                showNodeDetails.apply(that, [node]);
                            }).text(translate('UsersFlow_ActionShowDetails'));
                        }
                    }

                    if (isUrlLike(node.name) && !isSummaryNode(node.name)) {
                        list.append('li').attr('class', 'divider').html('<hr />')
                        list.append('li').attr('class', 'openPageUrl')
                            .append('a')
                            .attr('href', completeUrl(node.name))
                            .attr('rel', 'noreferrer')
                            .attr('target', '_blank')
                            .text(translate('Installation_SystemCheckOpenURL'));
                    }

                    popupMenu.style('left', (d3.event.pageX - 2) + 'px')
                        .style('top', (d3.event.pageY - 2) + 'px')
                        .style('display', 'block');
                }

                function highlightNodeTraffic(node, i) {
                    var remainingNodes = [],
                        nextNodes = [];

                    var $this = d3.select(this);

                    var stroke_opacity = 0, doHighlight;
                    if ($this.attr('data-clicked') == '1') {
                        $this.attr('data-clicked', '0');
                        doHighlight = false;
                    } else {
                        d3.select(this).attr('data-clicked', '1');
                        doHighlight = true;
                    }

                    $this.classed('highlightedNode', doHighlight);

                    var traverse = [{
                        linkType: 'sourceLinks',
                        nodeType: 'target'
                    }, {
                        linkType: 'targetLinks',
                        nodeType: 'source'
                    }];

                    traverse.forEach(function (step) {
                        node[step.linkType].forEach(function (link) {
                            if (isOutNode(link.target.name)) {
                                return;
                            }
                            remainingNodes.push(link[step.nodeType]);
                            highlightLink(link.id, doHighlight);
                        });

                        while (remainingNodes.length) {
                            nextNodes = [];
                            remainingNodes.forEach(function (node) {
                                node[step.linkType].forEach(function (link) {
                                    if (isOutNode(link.target.name)) {
                                        return;
                                    }
                                    nextNodes.push(link[step.nodeType]);
                                    highlightLink(link.id, doHighlight);
                                });
                            });
                            remainingNodes = nextNodes;
                        }
                    });
                }

                function highlightLink(id, doHighlight) {
                    d3.select('#link-' + id).classed('highlightedLink', doHighlight);
                }

                if (self.numSteps < self.maxSankeyChartDepth) {
                    var btnNextStep = svg.append('g').attr('class', 'addNewStepContainer').on('click', function () {
                        addSankeyStep();
                        setTimeout(function () {
                            var width = $('.sankeyChartOuter > div').width()
                            if (width) {
                                $('.sankeyChartOuter').animate({
                                    scrollLeft: width - 3
                                });
                            }
                        }, 20);
                    });
                    btnNextStep.append('path')
                        .attr('d', 'M512 960c-282.752 0-512-229.248-512-512s229.248-512 512-512 512 229.248 512 512-229.248 512-512 512zM682.688 362.688h-85.376v-85.312c0-47.168-38.208-85.376-85.312-85.376s-85.312 38.208-85.312 85.312v85.376h-85.376c-47.104 0-85.312 38.208-85.312 85.312s38.208 85.312 85.312 85.312h85.312v85.376c0.064 47.104 38.272 85.312 85.376 85.312s85.312-38.208 85.312-85.312v-85.312h85.312c47.168-0.064 85.376-38.272 85.376-85.376s-38.208-85.312-85.312-85.312z')
                        .attr('dx', width - 50)
                        .attr('dy', -30)
                        .attr('transform', 'translate(' + (width - 50) +',-66) scale(0.04)')
                        .attr('text-anchor', 'middle')
                        .attr('class', 'addNewStep').append('title').text(translate('UsersFlow_ActionAddStep'));

                    btnNextStep.append('rect')
                        .attr('x', width - 50)
                        .attr('y', '-69')
                        .attr('width', '40')
                        .attr('height', '40')
                        .attr('fill', 'transparent')
                        .style('cursor', 'pointer')
                        .append('title').text(translate('UsersFlow_ActionAddStep'));
                }

            }

        }

        function buildNodesAndIndexes(response)
        {
            self.maxSankeyChartDepth = 0;
            self.maxNodeLength = 0;

            var links = [];
            var nodes = [];
            var depthNodes = [];
            var depth;

            var i = 0, j = 0, k = 0, nodeIndex = 0;

            for (i = 0; i < response.length; i++) {
                depth = response[i].label;
                if (depth > self.maxSankeyChartDepth) {
                    setMaxSankeyChartDepth(depth);
                }
            }

            if (self.numSteps > self.maxSankeyChartDepth) {
                // we need to reset numsteps automatically if api for some reason returns less steps
                // eg when exploring traffic
                self.numSteps = self.maxSankeyChartDepth;
            }

            for (i = 0; i < response.length; i++) {
                var depthRow = response[i];
                depth = depthRow.label;

                if (!depthRow.subtable) {
                    continue;
                }

                if ((depthRow.subtable.length + 1) > self.maxNodeLength) {
                    setMaxNodeLength(depthRow.subtable.length + 1); // +1 for out node
                }

                if (depth > self.numSteps) {
                    // we make sure to only show as many interactions as requested
                    continue;
                }

                var depthNode = {
                    depth: depth - 1,
                    in: 0,
                    out: 0,
                    totalIn: depthRow.nb_visits,
                    totalOut: depthRow.nb_proceeded,
                    totalExits: depthRow.nb_exits,
                };

                j = 0;
                for (j; j < depthRow.subtable.length; j++) {
                    var sourceRow = depthRow.subtable[j];
                    var sourceLabel = sourceRow.label;

                    if (!isSummaryNode(sourceLabel)) {
                        // here we want to count the values only for the nodes shown
                        depthNode.in += sourceRow.nb_visits;
                        depthNode.out += sourceRow.nb_proceeded;
                    }

                    nodes.push({
                        depth: depth - 1,
                        name: sourceLabel,
                        node: nodeIndex,
                        totalIn: sourceRow.nb_visits,
                        totalOut: sourceRow.nb_proceeded,
                        totalExits: sourceRow.nb_exits,
                        pagesInGroup: sourceRow.nb_pages_in_group ? sourceRow.nb_pages_in_group : 0,
                        isSummaryNode: isSummaryNode(sourceLabel),
                        idSubtable: sourceRow.idsubdatatable ? sourceRow.idsubdatatable : null
                    });
                    // nb_pages_in_group is available for summary rows only so far

                    nodeIndex++;

                    if (depth >= self.numSteps) {
                        // we do not add links for the last interaction position
                        continue;
                    }

                    if (!sourceRow.subtable) {
                        // no subtable, no links
                        continue;
                    }

                    k = 0;
                    for (k; k < sourceRow.subtable.length; k++) {
                        var targetRow = sourceRow.subtable[k];
                        var targetLabel = targetRow.label;

                        links.push({
                            depth: depth,
                            source: nodeIndex - 1, // -1 cause we already did nodeIndex++ before
                            target: targetLabel,
                            value: targetRow.nb_visits
                        });
                    }

                    if (sourceRow.nb_exits) {
                        // we are also adding a link to the out node of the next step if there were exits
                        links.push({
                            depth: depth,
                            source: nodeIndex - 1, // -1 cause we already did nodeIndex++ before
                            target: OUT_NODE_NAME,
                            value: sourceRow.nb_exits
                        });
                    }
                }

                depthNodes.push(depthNode);

                if (depth > 1) {
                    nodes.push({
                        depth: depth - 1,
                        name: OUT_NODE_NAME,
                        node: nodeIndex,
                        value: 0,
                        totalIn: 0
                    });

                    nodeIndex++;
                }
            }

            // now we need to replace the target labels with proper target node ids
            angular.forEach(links, function (link) {
                some(nodes, function (element) {
                    if (link.target == element.name && link.depth == element.depth) {
                        link.target = element.node;
                        return true;
                    }
                });
            });

            return {
                nodes: nodes,
                links: links,
                depthNodes: depthNodes
            };
        }

        this.updateViewParams = function()
        {
            var parameters = {
                numActionsPerStep: this.numActionsPerStep,
                levelOfDetail: this.levelOfDetail,
                userFlowSource: this.userFlowSource
            };
            piwikApi.withTokenInUrl();
            piwikApi.post({
                module: 'CoreHome',
                action: 'saveViewDataTableParameters',
                report_id: 'UsersFlow.getUsersFlow'
            }, {
                parameters: JSON.stringify(parameters)
            });
        }

        function fetchData() {
            clearSankeyCahrt();

            if (self.exploreStep && self.exploreUrl) {
                self.isExploringTraffic = true;
            } else {
                self.isExploringTraffic = false;
            }

            self.isLoading = true;
            self.rawResponse = [];

            var params = {
                method: 'UsersFlow.getUsersFlow',
                expanded: '1',
                filter_limit: '-1',
                dataSource: self.userFlowSource,
                limitActionsPerStep: self.numActionsPerStep
            };

            if (self.exploreStep && self.exploreUrl) {
                params.exploreStep = self.exploreStep;
                params.exploreUrl = self.exploreUrl;
            }

            piwikApi.fetch(params).then(function (response) {
                self.isLoading = false;
                self.rawResponse = response;

                clearSankeyCahrt();

                if (response && response.length > 0) {
                    var nodesAndLinks = buildNodesAndIndexes(self.rawResponse);
                    drawSankeyChart(nodesAndLinks);
                } else {
                    self.hasData = false;
                }
            });
        }

        $scope.$watch('usersFlow.numActionsPerStep', function (newValue, oldValue) {
            if (newValue === null) {
                return;
            }
            if (newValue != oldValue) {
                fetchData();
                self.updateViewParams();
            }
        });

        $scope.$watch('usersFlow.userFlowSource', function (newValue, oldValue) {
            if (newValue === null) {
                return;
            }
            if (newValue != oldValue) {
                fetchData();
                self.updateViewParams();
            }
        });

        $scope.$on('$destroy', function() {
            clearSankeyCahrt();
        });

        fetchData();
    };

})();