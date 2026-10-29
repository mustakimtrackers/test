/**
 * Dashboard Charts JavaScript
 * Chart.js initialization with premium styling
 */

document.addEventListener('DOMContentLoaded', function () {
    // Chart.js default config
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-secondary');

    // Initialize all charts
    initSalesTrendChart();
    initOrderStatusChart();
    initTopProductsChart();
});

/**
 * Sales Trend Chart (Line Chart)
 */
async function initSalesTrendChart() {
    const ctx = document.getElementById('salesTrendChart');
    if (!ctx) return;

    // Fetch sales data (last 30 days)
    const salesData = await fetch(BASE_PATH + '/modules/dashboard/api/sales_trend.php')
        .then(res => res.json())
        .catch(() => generateMockSalesData(30));

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: salesData.labels,
            datasets: [{
                label: 'Revenue',
                data: salesData.values,
                borderColor: 'rgba(99, 102, 241, 1)',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#fff',
                pointBorderColor: 'rgba(99, 102, 241, 1)',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    borderRadius: 8,
                    titleFont: {
                        size: 13,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 14,
                        weight: '700'
                    },
                    callbacks: {
                        label: function (context) {
                            return '৳' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        callback: function (value) {
                            return '৳' + value.toLocaleString();
                        },
                        padding: 10
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        padding: 10
                    }
                }
            }
        }
    });
}

/**
 * Order Status Distribution Chart (Doughnut Chart)
 */
async function initOrderStatusChart() {
    const ctx = document.getElementById('orderStatusChart');
    if (!ctx) return;

    // Fetch order status data
    const statusData = await fetch(BASE_PATH + '/modules/dashboard/api/order_status.php')
        .then(res => res.json())
        .catch(() => ({
            completed: 156,
            pending: 23,
            canceled: 8
        }));

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Pending', 'Canceled'],
            datasets: [{
                data: [statusData.completed, statusData.pending, statusData.canceled],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',  // Success
                    'rgba(245, 158, 11, 0.8)',  // Warning
                    'rgba(239, 68, 68, 0.8)'    // Danger
                ],
                borderColor: [
                    'rgba(16, 185, 129, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(239, 68, 68, 1)'
                ],
                borderWidth: 2,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        font: {
                            size: 13,
                            weight: '600'
                        },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    borderRadius: 8,
                    titleFont: {
                        size: 13,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 14,
                        weight: '700'
                    },
                    callbacks: {
                        label: function (context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
}

/**
 * Top 5 Products Chart (Horizontal Bar Chart)
 */
async function initTopProductsChart() {
    const ctx = document.getElementById('topProductsChart');
    if (!ctx) return;

    //Fetch top products data
    const productsData = await fetch(BASE_PATH + '/modules/dashboard/api/top_products.php')
        .then(res => res.json())
        .catch(() => generateMockProductsData());

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: productsData.labels,
            datasets: [{
                label: 'Units Sold',
                data: productsData.values,
                backgroundColor: [
                    'rgba(99, 102, 241, 0.8)',
                    'rgba(139, 92, 246, 0.8)',
                    'rgba(236, 72, 153, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(16, 185, 129, 0.8)'
                ],
                borderColor: [
                    'rgba(99, 102, 241, 1)',
                    'rgba(139, 92, 246, 1)',
                    'rgba(236, 72, 153, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(16, 185, 129, 1)'
                ],
                borderWidth: 2,
                borderRadius: 8,
                barThickness: 40
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    borderRadius: 8,
                    titleFont: {
                        size: 13,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 14,
                        weight: '700'
                    },
                    callbacks: {
                        label: function (context) {
                            return context.parsed.x + ' units';
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        padding: 10
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        padding: 10
                    }
                }
            }
        }
    });
}

/**
 * Generate mock sales data (fallback)
 */
function generateMockSalesData(days) {
    const labels = [];
    const values = [];
    const today = new Date();

    for (let i = days - 1; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(date.getDate() - i);
        labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        values.push(Math.random() * 5000 + 1000);
    }

    return { labels, values };
}

/**
 * Generate mock products data (fallback)
 */
function generateMockProductsData() {
    return {
        labels: ['Samsung Galaxy S23', 'iPhone 14 Pro', 'MacBook Air M2', 'AirPods Pro', 'iPad Air'],
        values: [45, 38, 32, 28, 24]
    };
}

// Period selector for sales chart
const periodSelector = document.getElementById('salesPeriod');
if (periodSelector) {
    periodSelector.addEventListener('change', function () {
        // Reload chart with new period
        const chart = Chart.getChart('salesTrendChart');
        if (chart) {
            chart.destroy();
        }
        initSalesTrendChart();
    });
}

// Financial Privacy Toggle
const togglePrivacyBtn = document.getElementById('togglePrivacy');
const sensitiveAmounts = document.querySelectorAll('.sensitive-amount');

// Check local storage for preference
const isPrivacyEnabled = localStorage.getItem('financial_privacy') === 'true';
if (isPrivacyEnabled) {
    sensitiveAmounts.forEach(el => el.classList.add('blur-content'));
    if(togglePrivacyBtn) togglePrivacyBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
}

if (togglePrivacyBtn) {
    togglePrivacyBtn.addEventListener('click', function() {
        if(sensitiveAmounts.length === 0) return;
        const isCurrentlyHidden = sensitiveAmounts[0].classList.contains('blur-content');
        
        if (isCurrentlyHidden) {
            sensitiveAmounts.forEach(el => el.classList.remove('blur-content'));
            togglePrivacyBtn.innerHTML = '<i class="bi bi-eye"></i>';
            localStorage.setItem('financial_privacy', 'false');
        } else {
            sensitiveAmounts.forEach(el => el.classList.add('blur-content'));
            togglePrivacyBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
            localStorage.setItem('financial_privacy', 'true');
        }
    });
}

// Global Date Filter for Revenue
const revenueRangeSelector = document.getElementById('revenueRange');
if (revenueRangeSelector) {
    revenueRangeSelector.addEventListener('change', async function() {
        const range = this.value;
        const revenueEl = document.getElementById('dashboardRevenue');
        const revenueChangeEl = document.getElementById('dashboardRevenueChange');
        
        // Add loading state
        revenueEl.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
        
        try {
            const response = await fetch(`${BASE_PATH}/modules/dashboard/api/metrics.php?range=${range}`);
            const data = await response.json();
            
            if (data.success) {
                revenueEl.textContent = data.formatted_revenue;
                
                // Reapply blur if privacy is on
                if (localStorage.getItem('financial_privacy') === 'true') {
                    revenueEl.classList.add('blur-content');
                }
                
                // Update trend UI
                let trendIcon = '<i class="bi bi-dash"></i>';
                if (data.direction === 'positive') trendIcon = '<i class="bi bi-arrow-up"></i>';
                else if (data.direction === 'negative') trendIcon = '<i class="bi bi-arrow-down"></i>';
                
                revenueChangeEl.className = `metric-change ${data.direction}`;
                revenueChangeEl.innerHTML = `${trendIcon} ${data.change_percent}% from previous`;
            }
        } catch (error) {
            console.error('Error fetching revenue metrics', error);
            revenueEl.textContent = 'Error';
        }
    });
}

// --------------------------------------------------------------------------
// Live Clock & Weather Widget
// --------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    const clockEl = document.getElementById('liveClock');
    const dateEl = document.getElementById('liveDate');
    const weatherEl = document.getElementById('weatherWidget');
    const widgetContainer = document.querySelector('.dashboard-info-widget');
    
    if (!clockEl || !widgetContainer) return;
    
    // 1. Live Clock
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        
        hours = hours < 10 ? '0' + hours : hours;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        const timeString = `${hours}:${minutes}:${seconds} <span class="text-muted fs-5 fw-normal">${ampm}</span>`;
        if (clockEl) clockEl.innerHTML = timeString;
        
        const mobileClockEl = document.getElementById('mobileClock');
        if (mobileClockEl) mobileClockEl.innerHTML = timeString;
        
        // Update Date at midnight
        if (hours === '12' && minutes === '00' && seconds === '00' && ampm === 'AM') {
            if (dateEl) {
                const options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
                dateEl.textContent = now.toLocaleDateString('en-US', options);
            }
            
            const mobileDateEl = document.getElementById('mobileDate');
            if (mobileDateEl) {
                const options = { weekday: 'short', day: '2-digit', month: 'short' }; // e.g. "Fri, 04 Sep"
                mobileDateEl.textContent = now.toLocaleDateString('en-US', options);
            }
        }
    }
    
    setInterval(updateClock, 1000);
    updateClock(); // initial call
    
    // 2. Weather Fetch (Open-Meteo API - Free, No Key Required)
    const lat = widgetContainer.dataset.lat;
    const lon = widgetContainer.dataset.lon;
    const city = widgetContainer.dataset.city;
    const prevBtn = document.getElementById('weatherPrev');
    const nextBtn = document.getElementById('weatherNext');
    let forecastData = [];
    let currentForecastIndex = 0;
    
    // Helper to map WMO Weather interpretation codes to icons
    function getWeatherIcon(code, isDay) {
        if (code === 0) return isDay ? 'bi-sun' : 'bi-moon-stars';
        if (code === 1 || code === 2 || code === 3) return isDay ? 'bi-cloud-sun' : 'bi-cloud-moon';
        if (code >= 45 && code <= 48) return 'bi-cloud-fog2';
        if (code >= 51 && code <= 67) return 'bi-cloud-drizzle';
        if (code >= 71 && code <= 77) return 'bi-snow';
        if (code >= 80 && code <= 82) return 'bi-cloud-rain';
        if (code >= 95) return 'bi-cloud-lightning-rain';
        return 'bi-cloud';
    }
    
    function renderWeather() {
        if (!forecastData.length) return;
        const data = forecastData[currentForecastIndex];
        const iconClass = getWeatherIcon(data.code, data.isDay);
        
        weatherEl.innerHTML = `<i class="bi ${iconClass} fs-5"></i> ${data.temp}°C<br><small class="text-muted" style="font-size:10px; line-height: 1;">${data.label}</small>`;
        
        if (prevBtn && nextBtn) {
            prevBtn.style.display = currentForecastIndex > 0 ? 'block' : 'none';
            nextBtn.style.display = currentForecastIndex < forecastData.length - 1 ? 'block' : 'none';
        }
    }
    
    if (prevBtn) prevBtn.addEventListener('click', () => { if (currentForecastIndex > 0) { currentForecastIndex--; renderWeather(); } });
    if (nextBtn) nextBtn.addEventListener('click', () => { if (currentForecastIndex < forecastData.length - 1) { currentForecastIndex++; renderWeather(); } });
    
    async function fetchWeather() {
        try {
            const response = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&daily=weathercode,temperature_2m_max,temperature_2m_min&timezone=auto`);
            if (!response.ok) throw new Error('Weather API failed');
            
            const data = await response.json();
            forecastData = [];
            
            // Current
            forecastData.push({
                temp: Math.round(data.current_weather.temperature),
                code: data.current_weather.weathercode,
                isDay: data.current_weather.is_day,
                label: city + ' (Now)'
            });
            
            // Forecasts
            if (data.daily && data.daily.time) {
                for (let i = 1; i <= 2; i++) {
                    if (data.daily.time[i]) {
                        const dateObj = new Date(data.daily.time[i]);
                        const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
                        const avgTemp = Math.round((data.daily.temperature_2m_max[i] + data.daily.temperature_2m_min[i]) / 2);
                        forecastData.push({
                            temp: avgTemp,
                            code: data.daily.weathercode[i],
                            isDay: 1,
                            label: city + ' (' + dayName + ')'
                        });
                    }
                }
            }
            
            currentForecastIndex = 0;
            renderWeather();
            
        } catch (error) {
            console.error('Weather fetch error:', error);
            weatherEl.innerHTML = `<i class="bi bi-cloud-slash"></i> Unavailable`;
            weatherEl.classList.remove('text-primary');
            weatherEl.classList.add('text-muted');
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
        }
    }
    
    fetchWeather();
    // Refresh weather every 30 minutes
    setInterval(fetchWeather, 30 * 60 * 1000);
});

