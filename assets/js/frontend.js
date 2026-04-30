/* CHM Appointments — Frontend Booking JS (per-service pricing) */
(function($){
    'use strict';

    var state = {
        selectedDate: null,
        selectedTime: null,
        selectedTimeLabel: null,
        selectedService: null,
        selectedPrice: 0,
        currentMonth: new Date().getMonth(),
        currentYear: new Date().getFullYear()
    };

    var stripe, cardElement;

    /* ── Init ── */
    function init(){
        buildCalendar();
        initStripe();
        bindEvents();
    }

    /* ── Format price ── */
    function fmtPrice(n){
        return chmBooking.currencySym + parseFloat(n).toFixed(2);
    }

    /* ── Stripe setup ── */
    function initStripe(){
        if(!chmBooking.stripeKey) return;
        stripe = Stripe(chmBooking.stripeKey);
        var elements = stripe.elements({
            fonts:[{cssSrc:'https://fonts.googleapis.com/css2?family=Barlow:wght@400;500&display=swap'}]
        });
        cardElement = elements.create('card', {
            style: {
                base: { fontFamily:"'Barlow',sans-serif", fontSize:'16px', color:'#081524', '::placeholder':{color:'#98a7b5'} },
                invalid: { color:'#F07830' }
            }
        });
    }

    function mountCard(){
        if(cardElement && document.getElementById('chm-card-element')){
            cardElement.mount('#chm-card-element');
            cardElement.on('change', function(e){
                $('#chm-card-errors').text(e.error ? e.error.message : '');
            });
        }
    }

    /* ── Calendar ── */
    function buildCalendar(){
        var now=new Date(), year=state.currentYear, month=state.currentMonth,
            first=new Date(year,month,1), last=new Date(year,month+1,0),
            startDay=first.getDay(), daysInMonth=last.getDate();

        var names=['January','February','March','April','May','June','July','August','September','October','November','December'];
        $('.chm-cal-month-label').text(names[month]+' '+year);

        var $days=$('.chm-cal-days').empty();
        var dayMap={0:'sun',1:'mon',2:'tue',3:'wed',4:'thu',5:'fri',6:'sat'};
        var bizDays=chmBooking.businessDays||[], blocked=chmBooking.blockedDates||[];
        var minNotice=parseInt(chmBooking.minNotice)||0, maxAdvance=parseInt(chmBooking.maxAdvance)||60;
        var today=new Date(); today.setHours(0,0,0,0);
        var minDate=new Date(now.getTime()+minNotice*3600*1000); minDate.setHours(0,0,0,0);
        var maxDate=new Date(today.getTime()+maxAdvance*86400000);

        for(var e=0;e<startDay;e++) $days.append('<div class="chm-cal-day chm-cal-day--empty"></div>');

        for(var d=1;d<=daysInMonth;d++){
            var dt=new Date(year,month,d);
            var ds=year+'-'+String(month+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');
            var dow=dayMap[dt.getDay()];
            var isToday=dt.getTime()===today.getTime();
            var off=dt<minDate||dt>maxDate||bizDays.indexOf(dow)===-1||blocked.indexOf(ds)!==-1;
            var sel=state.selectedDate===ds;
            var cls='chm-cal-day';
            if(isToday) cls+=' chm-cal-day--today';
            if(off) cls+=' chm-cal-day--disabled';
            if(sel) cls+=' chm-cal-day--selected';
            $days.append('<div class="'+cls+'" data-date="'+ds+'">'+d+'</div>');
        }
    }

    /* ── Fetch slots ── */
    function fetchSlots(date){
        var $list=$('.chm-slots-list').hide().empty(),
            $load=$('.chm-slots-loading').show(),
            $empty=$('.chm-slots-empty').hide(),
            $ph=$('.chm-slots-placeholder').hide();

        state.selectedTime=null; state.selectedTimeLabel=null;
        updateContinueBtn();

        $.post(chmBooking.ajax,{action:'chm_get_slots',nonce:chmBooking.nonce,date:date},function(res){
            $load.hide();
            if(!res.success||!res.data.length){$empty.show();return;}
            var avail=0;
            res.data.forEach(function(s){
                var c='chm-slot'; if(!s.available) c+=' chm-slot--taken';
                $list.append('<div class="'+c+'" data-time="'+s.time+'" data-label="'+s.label+'">'+s.label+(s.available?'':' (booked)')+'</div>');
                if(s.available) avail++;
            });
            if(avail===0) $empty.show(); else $list.show();
        });
    }

    function updateContinueBtn(){
        $('[data-next="2"]').prop('disabled',!(state.selectedDate&&state.selectedTime));
    }

    /* ── Steps ── */
    function goToStep(n){
        $('.chm-step').removeClass('chm-step--active');
        $('.chm-step[data-step="'+n+'"]').addClass('chm-step--active');

        if(n===3){
            mountCard();
            var dateObj=new Date(state.selectedDate+'T12:00:00');
            var opts={weekday:'long',year:'numeric',month:'long',day:'numeric'};
            $('#chm-sum-date').text(dateObj.toLocaleDateString('en-US',opts));
            $('#chm-sum-time').text(state.selectedTimeLabel);
            $('#chm-sum-service').text(state.selectedService);
            $('#chm-sum-total').text(fmtPrice(state.selectedPrice));
            $('#chm-pay-btn .chm-btn-text').html('Pay '+fmtPrice(state.selectedPrice)+' &amp; Book');
        }
        $('html,body').animate({scrollTop:$('#chm-booking-app').offset().top-80},300);
    }

    function validateDetails(){
        var n=$('#chm-name').val().trim(), e=$('#chm-email').val().trim(), s=$('#chm-service').val();
        if(!n){alert('Please enter your name.');return false;}
        if(!e||!/\S+@\S+\.\S+/.test(e)){alert('Please enter a valid email.');return false;}
        if(!s){alert('Please select a service.');return false;}
        return true;
    }

    /* ── Payment ── */
    function processPayment(){
        var $btn=$('#chm-pay-btn'), $text=$btn.find('.chm-btn-text'), $spin=$btn.find('.chm-btn-spinner');
        $btn.prop('disabled',true); $text.hide(); $spin.show();

        $.post(chmBooking.ajax,{
            action:'chm_create_payment_intent', nonce:chmBooking.nonce,
            name:$('#chm-name').val().trim(), email:$('#chm-email').val().trim(),
            service:state.selectedService, date:state.selectedDate, time:state.selectedTime
        },function(res){
            if(!res.success){
                showPayError(res.data||'Payment initialization failed.');
                $btn.prop('disabled',false);$text.show();$spin.hide(); return;
            }
            stripe.confirmCardPayment(res.data.clientSecret,{
                payment_method:{card:cardElement,billing_details:{name:$('#chm-name').val().trim(),email:$('#chm-email').val().trim()}}
            }).then(function(result){
                if(result.error){
                    showPayError(result.error.message);
                    $btn.prop('disabled',false);$text.show();$spin.hide(); return;
                }
                if(result.paymentIntent.status==='succeeded') createBooking(result.paymentIntent.id);
            });
        }).fail(function(){
            showPayError('Network error. Please try again.');
            $btn.prop('disabled',false);$text.show();$spin.hide();
        });
    }

    function createBooking(paymentId){
        $.post(chmBooking.ajax,{
            action:'chm_create_booking', nonce:chmBooking.nonce,
            name:$('#chm-name').val().trim(), email:$('#chm-email').val().trim(),
            phone:$('#chm-phone').val().trim(), service:state.selectedService,
            date:state.selectedDate, time:state.selectedTime,
            notes:$('#chm-notes').val().trim(), payment_id:paymentId
        },function(res){
            if(res.success) showConfirmation(res.data);
            else{
                showPayError('Payment processed but booking failed. Reference: '+paymentId);
                $('#chm-pay-btn').prop('disabled',false).find('.chm-btn-text').show();
                $('#chm-pay-btn .chm-btn-spinner').hide();
            }
        });
    }

    function showConfirmation(d){
        $('#chm-confirm-msg').text(chmBooking.successMsg);
        var h='';
        h+='<div class="chm-cd-row"><span>Date</span><strong>'+d.date+'</strong></div>';
        h+='<div class="chm-cd-row"><span>Time</span><strong>'+d.time+'</strong></div>';
        h+='<div class="chm-cd-row"><span>Service</span><strong>'+d.service+'</strong></div>';
        h+='<div class="chm-cd-row"><span>Amount</span><strong>'+d.amount+'</strong></div>';
        h+='<div class="chm-cd-row"><span>Booking ID</span><strong>#'+d.id+'</strong></div>';
        $('#chm-confirm-details').html(h);
        goToStep(4);
    }

    function showPayError(msg){ $('#chm-card-errors').text(msg); }

    /* ── Events ── */
    function bindEvents(){
        // Calendar nav
        $(document).on('click','.chm-cal-prev',function(){
            state.currentMonth--; if(state.currentMonth<0){state.currentMonth=11;state.currentYear--;}
            buildCalendar();
        });
        $(document).on('click','.chm-cal-next',function(){
            state.currentMonth++; if(state.currentMonth>11){state.currentMonth=0;state.currentYear++;}
            buildCalendar();
        });

        // Date pick
        $(document).on('click','.chm-cal-day:not(.chm-cal-day--disabled):not(.chm-cal-day--empty)',function(){
            state.selectedDate=$(this).data('date');
            $('.chm-cal-day').removeClass('chm-cal-day--selected');
            $(this).addClass('chm-cal-day--selected');
            fetchSlots(state.selectedDate);
        });

        // Time pick
        $(document).on('click','.chm-slot:not(.chm-slot--taken)',function(){
            state.selectedTime=$(this).data('time');
            state.selectedTimeLabel=$(this).data('label');
            $('.chm-slot').removeClass('chm-slot--selected');
            $(this).addClass('chm-slot--selected');
            updateContinueBtn();
        });

        // Service select → update price preview
        $(document).on('change','#chm-service',function(){
            var svc=$(this).val();
            state.selectedService=svc;
            if(svc && chmBooking.services[svc] !== undefined){
                state.selectedPrice=chmBooking.services[svc];
                $('#chm-price-value').text(fmtPrice(state.selectedPrice));
                $('#chm-price-preview').show();
            } else {
                state.selectedPrice=0;
                $('#chm-price-preview').hide();
            }
        });

        // Step nav
        $(document).on('click','.chm-btn--next',function(){
            var next=parseInt($(this).data('next'));
            if(next===3){
                if(!validateDetails()) return;
                if(state.selectedPrice<=0){alert('Please select a valid service.');return;}
            }
            goToStep(next);
        });
        $(document).on('click','.chm-btn--back',function(){ goToStep(parseInt($(this).data('back'))); });

        // Pay
        $(document).on('click','#chm-pay-btn',function(){ processPayment(); });
    }

    $(init);
})(jQuery);
