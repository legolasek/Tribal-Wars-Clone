// Funkcje pomocnicze JavaScript

/**
 * Formatuje liczbę z separatorami tysięcy
 * @param {number} number Liczba do sformatowania
 * @returns {string} Sformatowana liczba
 */
function formatNumber(number) {
    return new Intl.NumberFormat('pl-PL').format(number);
}

/**
 * Formatuje czas w sekundach do formatu HH:MM:SS
 * @param {number} seconds Czas w sekundach
 * @returns {string} Sformatowany czas
 */
function formatTime(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

/**
 * Formatuje czas pozostały do zakończenia zadania.
 * @param {number} finishTime Timestamp zakończenia (w sekundach).
 * @returns {string} Sformatowany czas pozostały (np. "1h 30m 15s").
 */
function getRemainingTimeText(finishTime) {
    if (finishTime === null) return '';
    const finishTimeMillis = finishTime * 1000;
    const currentTimeMillis = new Date().getTime();
    const remainingMillis = finishTimeMillis - currentTimeMillis;

    if (remainingMillis <= 0) return 'Zakończono!';

    const seconds = Math.floor((remainingMillis / 1000) % 60);
    const minutes = Math.floor((remainingMillis / (1000 * 60)) % 60);
    const hours = Math.floor((remainingMillis / (1000 * 60 * 60)) % 24);
    const days = Math.floor(remainingMillis / (1000 * 60 * 60 * 24));

    let timeString = '';
    if (days > 0) timeString += days + 'd ';
    if (hours > 0 || days > 0) timeString += hours + 'h ';
    timeString += minutes + 'm ' + seconds + 's';

    return timeString.trim();
}

// Tutaj można dodawać kolejne uniwersalne funkcje JS
