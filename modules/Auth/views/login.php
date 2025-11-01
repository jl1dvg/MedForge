<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<style>
    /* Solo aplica en el login */
    body.bg-img .container.h-p100 {
        min-height: 100vh; /* ocupa toda la altura visible */
        display: flex;
        align-items: center;
        justify-content: center;
    }

    body.bg-img .h-p100 > .row {
        width: 100%;
        margin: 0;
    }
</style>
<div class="container h-p100">
    <div class="row align-items-center justify-content-md-center h-p100">

        <div class="col-12">
            <div class="row justify-content-center g-0">
                <div class="col-lg-5 col-md-5 col-12">
                    <div class="bg-white rounded10 shadow-lg">
                        <div class="content-top-agile p-20 pb-0">
                            <h2 class="text-primary">Empecemos</h2>
                            <p class="mb-0">Inicia sesión para continuar a MedForge.</p>
                        </div>
                        <div class="p-40">
                            <?php if (!empty($error ?? null)): ?>
                                <div class="alert alert-danger text-center"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                            <?php elseif (isset($_GET['error'])): ?>
                                <div class="alert alert-danger text-center">Credenciales incorrectas.</div>
                            <?php endif; ?>
                            <form action="/auth/login" method="post">
                                <div class="form-group">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text bg-transparent"><i class="ti-user"></i></span>
                                        <label for="username" class="visually-hidden">Usuario</label>
                                        <input type="text" id="username" name="username"
                                               class="form-control ps-15 bg-transparent" placeholder="Username"
                                               required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text  bg-transparent"><i class="ti-lock"></i></span>
                                        <label for="password" class="visually-hidden">Contraseña</label>
                                        <input type="password" id="password" name="password"
                                               class="form-control ps-15 bg-transparent"
                                               placeholder="Password" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="checkbox">
                                            <input type="checkbox" id="basic_checkbox_1">
                                            <label for="basic_checkbox_1">Acuérdate de mí</label>
                                        </div>
                                    </div>
                                    <!-- /.col -->
                                    <div class="col-6">
                                        <div class="fog-pwd text-end">
                                            <a href="javascript:void(0)" class="hover-warning"><i
                                                        class="ion ion-locked"></i> ¿Olvidaste la contraseña?</a><br>
                                        </div>
                                    </div>
                                    <!-- /.col -->
                                    <div class="col-12 text-center">
                                        <button type="submit" class="btn btn-danger mt-10">INICIAR SESIÓN</button>
                                    </div>
                                    <!-- /.col -->
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

