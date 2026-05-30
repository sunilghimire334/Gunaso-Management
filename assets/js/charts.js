// Colors for charts
const chartColors = [
    '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', 
    '#06b6d4', '#84cc16', '#f97316', '#6366f1', '#ec4899',
    '#14b8a6', '#f43f5e'
];

// Initialize all charts
function initializeCharts() {
    initializeComplaintTypeChart();
    initializeResolutionChart();
}

// Complaint Type Chart
function initializeComplaintTypeChart() {
    const complaintTypeCtx = document.getElementById('complaintTypeChart');
    if (!complaintTypeCtx) return;
    
    const complaintTypeChart = new Chart(complaintTypeCtx.getContext('2d'), {
        type: 'pie',
        data: {
            labels: window.complaintTypesLabels || ['कुनै डाटा उपलब्ध छैन'],
            datasets: [{
                data: window.complaintTypesData || [1],
                backgroundColor: chartColors,
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: {
                            size: window.innerWidth < 768 ? 9 : 11
                        },
                        boxWidth: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            layout: {
                padding: {
                    top: 10,
                    bottom: 10
                }
            }
        }
    });
    
    return complaintTypeChart;
}

// Resolution Status Chart
function initializeResolutionChart() {
    const resolutionCtx = document.getElementById('resolutionChart');
    if (!resolutionCtx) return;
    
    const resolutionChart = new Chart(resolutionCtx.getContext('2d'), {
        type: 'pie',
        data: {
            labels: ['समाधान भएको', 'समाधान नभएको'],
            datasets: [{
                data: window.resolutionData || [0, 0],
                backgroundColor: ['#10b981', '#f59e0b'],
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: {
                            size: window.innerWidth < 768 ? 9 : 11
                        },
                        boxWidth: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            layout: {
                padding: {
                    top: 10,
                    bottom: 10
                }
            }
        }
    });
    
    return resolutionChart;
}

// Update charts on window resize
function handleResize() {
    // Charts will automatically resize due to responsive: true
    // This function can be used for any additional resize handling
}

// Export functions for global access
window.initializeCharts = initializeCharts;
window.handleResize = handleResize;