<div class="wrap">
    <h2>Test Results</h2>
    <div style="float: right;">
        <div># tests taken in last 30 days</div>
        <canvas id="myChart" width="400" height="400"></canvas>
    </div>
    <form>
        Date Range: From: <input id="tr-date1" type="date"> To: <input id="tr-date2" type="date"><br/>
        <button id="tr-show">Show Results</button>
        <button id="tr-full">Full Report</button>
    </form>

    <div id="tr-results">
        <table id="tr-table">
            <thead id="tr-head"></thead>
            <tbody id="tr-body"></tbody>
        </table>
        <a download="report.csv" href="#" onclick="return ExcellentExport.csv(this, 'tr-table');">Export to CSV</a>
    </div>
</div>

<script type="text/javascript">
    Date.prototype.toDateInputValue = (function() {
        var local = new Date(this);
        local.setMinutes(this.getMinutes() - this.getTimezoneOffset());
        return local.toJSON().slice(0, 10);
    });
    jQuery(document).ready(function($) {
        $.post(ajaxurl,
                {
                    action: 'trajax-summary',
                },
                function(data) {
                    var labels = [];
                    var datapoints = [];
                    $.each(data, function(index, value) {
                        labels.push(value.date);
                        datapoints.push(value.count);
                    });
                    var ctx = $("#myChart").get(0).getContext("2d");
                    var myNewChart = new Chart(ctx).Bar({
                        labels: labels,
                        datasets: [
                            {
                                label: "My First dataset",
                                fillolor: "rgba(220,220,220,0.5)",
                                strokeColor: "rgba(220,220,220,0.8)",
                                highlightFill: "rgba(55,55,55,0.75)",
                                highlightStroke: "rgba(220,220,220,1)",
                                data: datapoints
                            }
                        ]
                    });
                });
        $('#tr-date1').val(new Date().toDateInputValue());
        $('#tr-date2').val(new Date().toDateInputValue());
        $('#tr-show').click(function() {
            var d1 = encodeURIComponent($('#tr-date1').val());
            var d2 = encodeURIComponent($('#tr-date2').val());
            $.post(ajaxurl,
                    {
                        action: 'trajax-report',
                        date1: d1,
                        date2: d2
                    },
            function(data) {
                if (data.results.length == 0) {
                    $('#tr-head').html('');
                    $('#tr-body').html("<tr><td colspan='5'>No data for supplied date range</td></tr>");
                } else {
                    var r = '';
                    $.each(data.results, function(key, val) {
                        r += "<tr>";
                        r += "<td>" + val.student + "</td>";
                        r += "<td>" + val.test + "</td>";
                        r += "<td align='center'>" + val.score + "</td>";
                        r += "<td>" + val.testnumber + "</td>";
                        r += "<td>" + val.tracking + "</td>";
                        r += "<td>" + val.submitted + "</td>";
                        r += "</tr>";
                    });
                    $('#tr-head').html('<tr><th>Student</th><th>Test</th><th>Score</th><th>Test #</th><th>Tracking #</th><th>Submitted</th></tr>');
                    $('#tr-body').html(r);
                }
            });
            return false;
        });
        $('#tr-full').click(function() {
            var d1 = encodeURIComponent($('#tr-date1').val());
            var d2 = encodeURIComponent($('#tr-date2').val());
            $.post(ajaxurl,
                    {
                        action: 'trajax-full',
                        date1: d1,
                        date2: d2
                    },
            function(data) {
                if (data.results.length == 0) {
                    $('#tr-head').html('');
                    $('#tr-body').html("<tr><td colspan='5'>No data for supplied date range</td></tr>");
                } else {
                    var r = '';
                    $.each(data.results, function(key, val) {
                        r += "<tr>";
                        r += "<td>" + val.name + "</td>";

                        if (val.hasOwnProperty('Polynomials')) {
                            r += "<td>" + val.Polynomials.first + "</td>";
                            r += "<td>" + val.Polynomials.last + "</td>";
                        } else {
                            r += "<td>&nbsp</td><td>&nbsp</td>";
                        }

                        if (val.hasOwnProperty('Trigonometrics')) {
                            r += "<td>" + val.Trigonometrics.first + "</td>";
                            r += "<td>" + val.Trigonometrics.last + "</td>";
                        } else {
                            r += "<td>&nbsp</td><td>&nbsp</td>";
                        }

                        if (val.hasOwnProperty('Logarithm')) {
                            r += "<td>" + val.Logarithm.first + "</td>";
                            r += "<td>" + val.Logarithm.last + "</td>";
                        } else {
                            r += "<td>&nbsp</td><td>&nbsp</td>";
                        }

                        r += "</tr>";
                    });
                    var head = '<tr><th>&nbsp</th><th colspan="2">Polynomial & Rational Equation</th><th colspan="2">Trigonmetric Equation</th><th colspan="2">Exponential & Log Equation</th></tr>';
                    head += '<tr><th>Student</th><th>1st Attempt</th><th>Last Attempt</th><th>1st Attempt</th><th>Last Attempt</th><th>1st Attempt</th><th>Last Attempt</th></tr>';
                    $('#tr-head').html(head);
                    $('#tr-body').html(r);
                }
            });
            return false;
        });
    });

</script>