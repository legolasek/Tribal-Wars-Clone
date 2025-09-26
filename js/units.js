document.addEventListener('DOMContentLoaded', function() {
    const mainContent = document.getElementById('main-content');
    const popup = document.getElementById('building-action-popup');
    const popupDetails = document.getElementById('popup-details');
    const closeButton = popup.querySelector('.close-button');

    // Use event delegation for building action buttons
    mainContent.addEventListener('click', function(event) {
        const target = event.target.closest('.building-action-button');
        if (target) {
            const buildingName = target.dataset.buildingInternalName;
            const villageId = target.dataset.villageId;

            // Only handle military buildings here
            if (['barracks', 'stable', 'workshop'].includes(buildingName)) {
                openRecruitPanel(villageId, buildingName);
            }
        }
    });

    // Function to open the recruitment panel
    function openRecruitPanel(villageId, buildingName) {
        fetch(`/ajax/units/recruit.php?village_id=${villageId}&building=${buildingName}`)
            .then(response => response.text())
            .then(html => {
                popupDetails.innerHTML = html;
                popup.style.display = 'block';
                updateRecruitmentTimers();
            })
            .catch(error => {
                console.error('Error loading recruitment panel:', error);
                popupDetails.innerHTML = '<p class="error-message">Failed to load recruitment panel.</p>';
                popup.style.display = 'block';
            });
    }

    // Handle form submission for recruitment
    popupDetails.addEventListener('submit', function(event) {
        if (event.target.classList.contains('recruit-form')) {
            event.preventDefault();
            const form = event.target;
            const unitId = form.dataset.unitId;
            const villageId = form.dataset.villageId;
            const buildingName = form.dataset.buildingName;
            const count = form.querySelector('input[name="count"]').value;

            if (!count || count <= 0) {
                alert('Please enter a valid number of units to recruit.');
                return;
            }

            const data = {
                unit_id: unitId,
                count: parseInt(count, 10)
            };

            fetch(`/ajax/units/recruit.php?village_id=${villageId}&building=${buildingName}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    // Refresh the panel to show the updated queue
                    openRecruitPanel(villageId, buildingName);
                } else {
                    alert('Error: ' + result.error);
                }
            })
            .catch(error => {
                console.error('Error recruiting units:', error);
                alert('An error occurred while trying to recruit units.');
            });
        }
    });

    // Close popup
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            popup.style.display = 'none';
        });
    }

    window.addEventListener('click', (event) => {
        if (event.target === popup) {
            popup.style.display = 'none';
        }
    });


    // Function to update recruitment timers
    function updateRecruitmentTimers() {
        const timers = popupDetails.querySelectorAll('.timer[data-finish-time]');
        timers.forEach(timer => {
            const finishTime = parseInt(timer.dataset.finishTime, 10);
            const interval = setInterval(() => {
                const now = Math.floor(Date.now() / 1000);
                const remaining = finishTime - now;

                if (remaining <= 0) {
                    timer.textContent = 'Completed';
                    clearInterval(interval);
                    // Optionally, refresh the panel after a short delay
                    setTimeout(() => {
                        const form = popupDetails.querySelector('.recruit-form');
                        if(form) {
                           const villageId = form.dataset.villageId;
                           const buildingName = form.dataset.buildingName;
                           openRecruitPanel(villageId, buildingName);
                        }
                    }, 2000);
                } else {
                    timer.textContent = formatDuration(remaining);
                }
            }, 1000);
        });
    }

    // Helper function to format duration (should be in a shared JS file)
    function formatDuration(seconds) {
        if (seconds < 0) seconds = 0;
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return [h, m, s].map(v => v < 10 ? '0' + v : v).join(':');
    }
});