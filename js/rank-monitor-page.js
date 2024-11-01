jQuery(function($){
	function generateRankChart(placeholder, rankData){
		var maxVisibleRank = grmMaxGoogleResults + 2;
		
		var plot = $.plot(placeholder,
			//Data series 
			[{
				data: rankData,
				lines: {
					show: true,
					lineWidth: 4,
					fill: false //Generates area chart if true.
				},
				points: {
					lineWidth: 2,
					show: true,
					radius: 4
				},
				/*
				threshold: {
					below: grmMaxGoogleResults,
					color: "rgb(200, 20, 30)"
				},
				//*/
				color: 0
			}],
			
			//Options
			{
				xaxis: {
					mode: 'time',
					min: grmRankChartMin,
					max: grmRankChartMax
				},
				yaxis: {
					min: -1,
					max: maxVisibleRank,
					transform: function (value) { return -value; },
					inverseTransform: function (value) { return -value; },
					labelWidth: 15,
					ticks: function(axis){
						var result = [1], i = 1, tickSize;

						if ( axis.max >= 100 ){
							tickSize = 25;
						} else if ( axis.max >= 60 ){
							tickSize = 20;
						} else if (axis.max >= 20) {
							tickSize = 10;
						} else {
							tickSize = 5;
						}

						do {
						  var v = i * tickSize;
						  result.push([v, v.toFixed(0)]);
						  i++;
						} while (v < axis.max);
						return result;
					}
				},
				grid: {
					show: true,

					hoverable: true,
					autoHighlight: false,
					mouseActiveRadius: 300,

					borderWidth: 0,
					borderColor: '#777',
					markings: [
						/* Improvised X and Y axis markers */
						{
							yaxis: { from: maxVisibleRank, to: maxVisibleRank },
							color: '#777'
						},
						{
							xaxis: { from: grmRankChartMin, to: grmRankChartMin },
							color: '#777'
						}
						//*/
					]
				}
			}	
		);

		function showTooltip(x, y, data) {
			var chart = $("#big-rank-chart");
			var tooltip = $('#chart-tooltip');
			if ( tooltip.length == 0 ) {
				tooltip = $('<div id="chart-tooltip">&nbsp;</div>').appendTo(chart);
			}
			var contents = '<span class="rank">'+data[2]+'</span>';
			tooltip.html(contents).css( {
				top: y - 34 - chart.position().top,
				right: chart.width() + chart.position().left - x + 6
			}).show();

		}

		function hideTooltip(){
			$("#chart-tooltip").hide();
			plot.unhighlight();
		}

		var previousPoint = null;
		placeholder.bind('plothover', function(event, pos, item){
			if (item) {
				if ((previousPoint != item.dataIndex) && (previousPoint != null)) {
					plot.unhighlight();
				}
				previousPoint = item.dataIndex;

				hideTooltip();
				plot.highlight(item.series, item.datapoint);

				var x = item.datapoint[0].toFixed(0),
					y = item.datapoint[1].toFixed(0);
				showTooltip(item.pageX, item.pageY, grmRankHistory[item.dataIndex]);
			}
		});

		placeholder.mouseleave(hideTooltip);
	}
	
	if ( $('#big-rank-chart').length > 0 ){
		generateRankChart($('#big-rank-chart'), grmRankHistory );
	}
});