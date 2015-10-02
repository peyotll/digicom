<?php
/**
 * @package		DigiCom
 * @author 		ThemeXpert http://www.themexpert.com
 * @copyright	Copyright (c) 2010-2015 ThemeXpert. All rights reserved.
 * @license 	GNU General Public License version 3 or later; see LICENSE.txt
 * @since 		1.0.0
 */

defined ('_JEXEC') or die ("Go away.");

$configs = $this->configs;
$rangeDays = DigiComHelperChart::getRangeDayLabel($this->range);
$rangePrices = DigiComHelperChart::getRangePricesLabel($this->range,$rangeDays);
?>

<div><canvas id="myChart" width="400" height="150"></canvas></div>

<script type="text/javascript">
  var data = {
    labels: [<?php echo $rangeDays; ?>],
    datasets: [

      {
        label: "Range Report",
        fillColor: "#e6f3f9",
        strokeColor: "#1562AD",
        pointColor: "#1562AD",
        pointStrokeColor: "#1562AD",
        pointHighlightFill: "#e6f3f9",
        pointHighlightStroke: "#1562AD",
        data: [<?php echo $rangePrices; ?>]
      }
    ]
  };
  options ={
    animation: true,
    scaleShowLabels: true,
    responsive: true,
    tooltipTemplate: "<%if (label){%><%}%><%= value %> <?php echo $configs->get('currency','USD')?>",
  }
  var ctx = document.getElementById("myChart").getContext("2d");
  var myLineChart = new Chart(ctx).Line(data,options);
</script>
