/**
 * Animal Reports JavaScript
 * Handles interactive functionality for the animal reports page
 */

document.addEventListener('DOMContentLoaded', function() {
    // Tab Navigation
    const tabs = document.querySelectorAll('.nav-link');
    const tabContents = document.querySelectorAll('.tab-pane');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs and tab contents
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(content => {
                content.classList.remove('show', 'active');
            });
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Show corresponding tab content
            const target = this.getAttribute('href').substring(1);
            document.getElementById(target).classList.add('show', 'active');
            
            // Save active tab to localStorage
            localStorage.setItem('activeReportTab', target);
        });
    });
    
    // Check if there's a saved active tab in localStorage
    const activeTab = localStorage.getItem('activeReportTab');
    if (activeTab) {
        const tabToActivate = document.querySelector(`[href="#${activeTab}"]`);
        if (tabToActivate) {
            tabToActivate.click();
        }
    }
    
    // Date Range Picker Setup for Filtering (if element exists)
    if (typeof daterangepicker !== 'undefined' && document.getElementById('reportDateRange')) {
        $('#reportDateRange').daterangepicker({
            startDate: moment().subtract(30, 'days'),
            endDate: moment(),
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function(start, end, label) {
            // When date range changes, reload reports with new date range
            if (document.getElementById('dateRangeForm')) {
                document.getElementById('startDate').value = start.format('YYYY-MM-DD');
                document.getElementById('endDate').value = end.format('YYYY-MM-DD');
                document.getElementById('dateRangeForm').submit();
            }
        });
    }
    
    // Print Report Functionality
    const printButtons = document.querySelectorAll('.print-report');
    printButtons.forEach(button => {
        button.addEventListener('click', function() {
            const reportSection = this.closest('.card').querySelector('.card-body');
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank', 'height=600,width=800');
            
            // Set up the print document
            printWindow.document.write('<html><head><title>PureFarm - Animal Report</title>');
            printWindow.document.write('<link rel="stylesheet" href="assets/css/bootstrap.min.css">');
            printWindow.document.write('<style>body { padding: 20px; }</style>');
            printWindow.document.write('</head><body>');
            
            // Add report heading and date
            printWindow.document.write('<h1>PureFarm Management System</h1>');
            printWindow.document.write('<h2>' + this.dataset.reportTitle + '</h2>');
            printWindow.document.write('<p>Generated on: ' + new Date().toLocaleDateString() + '</p>');
            
            // Add report content
            printWindow.document.write('<div class="report-content">');
            printWindow.document.write(reportSection.innerHTML);
            printWindow.document.write('</div>');
            
            // Close document and initiate print
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            
            // Add slight delay before printing to ensure content is loaded
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 500);
        });
    });
    
    // Search/Filter Functionality for Tables
    const tableSearchInputs = document.querySelectorAll('.table-search');
    tableSearchInputs.forEach(input => {
        input.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableId = this.dataset.targetTable;
            const table = document.getElementById(tableId);
            
            if (table) {
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    let textContent = row.textContent.toLowerCase();
                    row.style.display = textContent.includes(searchTerm) ? '' : 'none';
                });
            }
        });
    });
    
    // Table Sorting Functionality
    const sortableHeaders = document.querySelectorAll('th.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const index = Array.from(this.parentNode.children).indexOf(this);
            const direction = this.classList.contains('asc') ? 'desc' : 'asc';
            
            // Clear existing sort indicators
            this.closest('tr').querySelectorAll('th').forEach(th => {
                th.classList.remove('asc', 'desc');
            });
            
            // Add appropriate sort indicator
            this.classList.add(direction);
            
            // Sort rows
            rows.sort((a, b) => {
                const aValue = a.children[index].textContent.trim();
                const bValue = b.children[index].textContent.trim();
                
                // Try to parse as numbers if possible
                const aNum = parseFloat(aValue);
                const bNum = parseFloat(bValue);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return direction === 'asc' ? aNum - bNum : bNum - aNum;
                }
                
                // Otherwise compare as strings
                return direction === 'asc' 
                    ? aValue.localeCompare(bValue) 
                    : bValue.localeCompare(aValue);
            });
            
            // Reattach rows in new order
            rows.forEach(row => tbody.appendChild(row));
        });
    });
});

/**
 * Initializes charts for the reports page
 * @param {Object} data - The data to populate the charts
 */
function initializeCharts(data) {
    // Only initialize charts if Chart.js is available
    if (typeof Chart !== 'undefined') {
        // Ensure data exists
        if (!data) return;
        
        // Species Distribution Chart
        if (document.getElementById('speciesChart') && data.speciesData) {
            const ctx = document.getElementById('speciesChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.speciesData.labels,
                    datasets: [{
                        data: data.speciesData.data,
                        backgroundColor: data.speciesData.colors,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                boxWidth: 12
                            }
                        }
                    }
                }
            });
        }
        
        // Health Trends Chart (if available)
        if (document.getElementById('healthTrendsChart') && data.healthTrends) {
            const ctx = document.getElementById('healthTrendsChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.healthTrends.labels,
                    datasets: data.healthTrends.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Breeding Success Rate Chart (if available)
        if (document.getElementById('breedingSuccessChart') && data.breedingData) {
            const ctx = document.getElementById('breedingSuccessChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.breedingData.labels,
                    datasets: [{
                        label: 'Success Rate (%)',
                        data: data.breedingData.rates,
                        backgroundColor: '#1cc88a'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    }
}