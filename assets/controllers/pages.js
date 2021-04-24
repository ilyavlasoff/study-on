$(document).ready(function () {
    let courseForms = $('.course-type-select');
    if(courseForms.length) {
        changeCourseTypePickersShow();

        courseForms.change(function () {
            changeCourseTypePickersShow();
        });

        function changeCourseTypePickersShow() {
            let type = courseForms.val();

            if(type === 'free') {
                $('.course-price-input').hide();
                $('.course-rent-time-picker').hide();
            } else if (type === 'rent') {
                $('.course-price-input').show();
                $('.course-rent-time-picker').show();
            } else {
                $('.course-price-input').show();
                $('.course-rent-time-picker').hide();
            }
        }
    }

    $('button[data-confirm]').click(function(event) {
        event.preventDefault();
        $('.modal-body').text($(this).attr('data-confirm'));
        $('.modal-ok').on('click', function() {
            $('form').submit();
        });
        $('.modal').modal({show:true});

    });

});