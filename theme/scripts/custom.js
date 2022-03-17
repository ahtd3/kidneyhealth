$(function() {
    $('.regional.tabs').each(function() {
        let heads = $(this).find('.regional__head li');
        let tabs = [];

        for(let el of heads) {
            let target = el.dataset.target;

            if(!target) continue;

            tabs.push({
                $head: $(el),
                $body: $(target)
            });
        }

        function activeTabBy(index) {
            tabs.forEach(function(i){
                i.$head.removeClass('active');
                i.$body.css({'display': ''});
            });

            tabs[index].$head.addClass('active');
            tabs[index].$body.fadeIn();
        }
        tabs.forEach(function(item, index) {
            item.$head.on('click', function() {
                activeTabBy(index);
            })
        });

        activeTabBy(1);
    });

    $('.regional.collapse').each(function() {
        let $itemOpen = undefined;
        const close = function() {
            if($itemOpen === undefined) return;
            $itemOpen.find('.regional__content').slideUp();
            $itemOpen.removeClass('active');
        }

        const open = function($element) {
            close();
            $itemOpen = $element;
            $itemOpen.find('.regional__content').slideDown();
            $itemOpen.addClass('active');
        }
        $(this).find('.regional__items .regional__head').on("click", function(e) {
            let contain = $(this).parents('.regional__items');
            if(contain.hasClass('active')) {
                close();
            } else {
                open(contain)
            }
        });

        open($(this).find('.regional__items').first());
    })
})

;(function(){
    Array.from(document.querySelectorAll('.image-overflow')).map(function(item){
        let media = item.dataset.media;
        if(isNaN(media)) return;
        media = parseInt(media);
        let c_observer = function() {
            if(window.outerWidth > media) {
                let height = item.querySelector('.block-content__wrap');
                item.querySelector('.image-block').style.minHeight = height.offsetHeight + 'px';
            } else {
                item.querySelector('.image-block').style.minHeight = '';
            }
        }
        c_observer();
        window.addEventListener('resize', c_observer);
    })
})();