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

// popup more info about member
;(function(){ 
    // start setup template
    // <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><!--! Font Awesome Pro 6.1.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2022 Fonticons, Inc. --><path d="M310.6 361.4c12.5 12.5 12.5 32.75 0 45.25C304.4 412.9 296.2 416 288 416s-16.38-3.125-22.62-9.375L160 301.3L54.63 406.6C48.38 412.9 40.19 416 32 416S15.63 412.9 9.375 406.6c-12.5-12.5-12.5-32.75 0-45.25l105.4-105.4L9.375 150.6c-12.5-12.5-12.5-32.75 0-45.25s32.75-12.5 45.25 0L160 210.8l105.4-105.4c12.5-12.5 32.75-12.5 45.25 0s12.5 32.75 0 45.25l-105.4 105.4L310.6 361.4z"/></svg>
    const template = `
        <div class="popup-info">
            <div class="popup-info__background" data-close></div>
            <div class="popup-info__wrap">
                <div class="popup-info__inner">
                    <div class="popup-info__close" data-close>✕</div>
                    <div class="popup-info__content">
                        
                    </div>
                </div>
            </div>
        </div>
    `

    document.body.insertAdjacentHTML("afterend", template)
    // end setup template

    $(() => {
        const popup = $('.popup-info');
        if(!popup) return;

        const close = popup.find('[data-close]');
        const content = popup.find('.popup-info__content');

        close.on('click', function() {
            popup.fadeOut(function() {
                popup.removeClass('open')
                document.body.style.paddingRight = ''
                document.body.classList.remove('popup-info-open')
            });
        })

        $('.popup-more-info').on('click', function() {
            const data = $(this).data('staff');
            if(!data) return;
            content.html(`
                <h3>${data.full_name}</h3>
                <div>
                    ${data.description}
                </div>
            `)
            
            let widScrollBar = window.innerWidth - document.body.offsetWidth;
            document.body.style.paddingRight = `${widScrollBar}px`
            document.body.classList.add('popup-info-open')
            popup.fadeIn(function() {
                popup.addClass('open');
            });
        })
    })
})();