<header>

        <div class="logo">
            <a href="<?php echo BASE_URL . '/index.php' ?>"><h1 class="logo-text"><span>Podróż</span>Smaków</h1></a>
        </div>
        
        <i class="fa fa-bars menu-toggle"></i>
        
        <ul class="nav">
            <!-- <li><a href="#">Załóż konto</a></li>
            <li><a href="#">Zaloguj się</a></li> -->
            <?php if(isset($_SESSION['username'])): ?>
                <li>
                    <a href="#">
                        <i class="fa fa-user"></i>
                        <?php echo $_SESSION['username']; ?>
                        <i class="fa fa-chevron-down" style="font-size: .8em;"></i>
                    </a>
                    <ul>
                        <li><a href="<?php echo BASE_URL . '/logout.php'; ?>" class="logout">Wyloguj się &nbsp;<i class="fas fa-sign-out"></i></a></li>
                    </ul>
                </li>
            <?php endif; ?>
            
        </ul>
    </header>