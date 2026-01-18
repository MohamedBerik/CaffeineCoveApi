<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="TemplateMo">
    <link href="https://fonts.googleapis.com/css?family=Poppins:100,200,300,400,500,600,700,800,900" rel="stylesheet">

    <title>Education Meeting HTML5 Template</title>

    <!-- Bootstrap core CSS -->
    <link href={{ asset('vendor/bootstrap/css/bootstrap.min.css') }} rel="stylesheet">


    <!-- Additional CSS Files -->
    <link rel="stylesheet" href={{ asset('assets/css/fontawesome.css') }}>
    <link rel="stylesheet" href={{ asset('assets/css/templatemo-edu-meeting.css') }}>
    <link rel="stylesheet" href={{ asset('assets/css/owl.css') }}>
    <link rel="stylesheet" href={{ asset('assets/css/lightbox.css') }}>

</head>

<body>
    <!-- ***** Header Area Start ***** -->
    <header class="header-area header-sticky">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <nav class="main-nav">
                        <!-- ***** Logo Start ***** -->
                        <a href="/" class="logo">
                            Edu Meeting
                        </a>
                        <!-- ***** Logo End ***** -->
                        <!-- ***** Menu Start ***** -->
                        <ul class="nav">
                            <li class="scroll-to-section"><a href="{{ route('welcome') }}" class="active">Home</a></li>
                            <li><a href="{{ route('meetings') }}">Meetings</a></li>
                            <li class="scroll-to-section"><a href="#apply">Apply Now</a></li>
                            <li class="has-sub">
                                <a href="javascript:void(0)">Pages</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('meetings') }}">Upcoming Meetings</a></li>
                                    <li><a href="{{ route('meeting-details') }}">Meeting Details</a></li>
                                </ul>
                            </li>
                            <li class="scroll-to-section"><a href="#courses">Courses</a></li>
                            <li class="scroll-to-section"><a href="#contact">Contact Us</a></li>
                            <li style="margin-top: -30px;">
                                @if (Route::has('login'))
                                    <div class="hidden fixed top-0 right-0 px-6 py-4 sm:block">
                                        @auth
                                            <form class="mt-1" method="post" action={{ route('logout') }}
                                                enctype="multipart/form-data">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-light">Logout</button>
                                            </form>
                                        @else
                                <li>
                                    <a href="{{ route('login') }}">Log in</a>
                                </li>
                                @if (Route::has('register'))
                                    <li>
                                        <a href="{{ route('register') }}">Register</a>
                                    </li>
                                @endif
                            @endauth
                </div>
                @endif
                </li>
                </ul>
                <a class='menu-trigger'>
                    <span>Menu</span>
                </a>
                <!-- ***** Menu End ***** -->
                </nav>
            </div>
        </div>
        </div>
    </header>
    <!-- ***** Header Area End ***** -->




    <main class="py-4">
        @yield('content')
    </main>




    <!-- Scripts -->
    <!-- Bootstrap core JavaScript -->
    <script src={{ asset('vendor/jquery/jquery.min.js') }}></script>
    <script src={{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}></script>

    <script src={{ asset('assets/js/isotope.min.js') }}></script>
    <script src={{ asset('assets/js/owl-carousel.js') }}></script>
    <script src={{ asset('assets/js/lightbox.js') }}></script>
    <script src={{ asset('assets/js/tabs.js') }}></script>
    <script src={{ asset('assets/js/video.js') }}></script>
    <script src={{ asset('assets/js/slick-slider.js') }}></script>
    <script src={{ asset('assets/js/custom.js') }}></script>
    <script>
        //according to loftblog tut
        $('.nav li:first').addClass('active');

        var showSection = function showSection(section, isAnimate) {
            var
                direction = section.replace(/#/, ''),
                reqSection = $('.section').filter('[data-section="' + direction + '"]'),
                reqSectionPos = reqSection.offset().top - 0;

            if (isAnimate) {
                $('body, html').animate({
                        scrollTop: reqSectionPos
                    },
                    800);
            } else {
                $('body, html').scrollTop(reqSectionPos);
            }

        };

        var checkSection = function checkSection() {
            $('.section').each(function() {
                var
                    $this = $(this),
                    topEdge = $this.offset().top - 80,
                    bottomEdge = topEdge + $this.height(),
                    wScroll = $(window).scrollTop();
                if (topEdge < wScroll && bottomEdge > wScroll) {
                    var
                        currentId = $this.data('section'),
                        reqLink = $('a').filter('[href*=\\#' + currentId + ']');
                    reqLink.closest('li').addClass('active').
                    siblings().removeClass('active');
                }
            });
        };

        $('.main-menu, .responsive-menu, .scroll-to-section').on('click', 'a', function(e) {
            e.preventDefault();
            showSection($(this).attr('href'), true);
        });

        $(window).scroll(function() {
            checkSection();
        });
    </script>
</body>

</html>
