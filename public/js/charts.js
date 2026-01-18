// charts.js - ملف منفصل يمكن إضافته مستقبلاً
function initializeCharts() {
    // مخطط المبيعات
    const salesCtx = document.getElementById("salesChart");
    if (salesCtx) {
        new Chart(salesCtx, {
            type: "line",
            data: {
                labels: ["يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو"],
                datasets: [
                    {
                        label: "المبيعات",
                        data: [12000, 19000, 15000, 25000, 22000, 30000],
                        borderColor: "#4e73df",
                        backgroundColor: "rgba(78, 115, 223, 0.1)",
                        tension: 0.4,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: "top",
                    },
                },
            },
        });
    }

    // مخطط المستخدمين
    const usersCtx = document.getElementById("usersChart");
    if (usersCtx) {
        new Chart(usersCtx, {
            type: "doughnut",
            data: {
                labels: ["المسؤولون", "المستخدمون", "العملاء"],
                datasets: [
                    {
                        data: [5, 45, 150],
                        backgroundColor: ["#4e73df", "#1cc88a", "#36b9cc"],
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: "right",
                    },
                },
            },
        });
    }
}

// استدعاء الدالة عند تحميل الصفحة
document.addEventListener("DOMContentLoaded", initializeCharts);
