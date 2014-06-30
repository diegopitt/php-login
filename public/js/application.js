$(document).ready(function() {

    // your stuff here
    // ...

});
$(window).on('resize load', function() {
    $('body').css({"padding-top": $(".navbar").height() + "px"});
});