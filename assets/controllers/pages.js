$(document).ready(function () {
    let cards = $('.card');
    let maxHeight = Math.max(cards.height());
    cards.height(maxHeight);
});