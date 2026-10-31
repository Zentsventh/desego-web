import React from 'react';

export default function AdminAuthScreen() {
    return (
        <main id="authScreen" className="auth-screen">
            <div className="auth-orb auth-orb-one" />
            <div className="auth-orb auth-orb-two" />

            {/* Panel de Inicio de Sesión */}
            <section id="loginPanel" className="auth-panel">
                <div className="auth-brand">
                    <img
                        className="auth-logo-image"
                        src="/assets/desego-logo.png"
                        alt="Logo de DESEGO CEVICHERÍA"
                    />
                    <div className="auth-brand-name">DESEGO</div>
                    <div className="auth-brand-subtitle">CEVICHERÍA · ENTERPRISE</div>
                </div>
                <div className="auth-intro">
                    <span className="auth-kicker">PANEL ADMINISTRATIVO</span>
                    <h1>Bienvenido a DESEGO</h1>
                    <p>Inicia sesión con tus credenciales corporativas para gestionar la operación del restaurante.</p>
                </div>
                <form id="loginForm" className="auth-form">
                    <label className="auth-label" htmlFor="loginEmail">
                        Correo electrónico corporativo
                    </label>
                    <div className="auth-input-wrap">
                        <span className="field-icon" aria-hidden="true">
                            ✉
                        </span>
                        <input
                            id="loginEmail"
                            type="email"
                            placeholder="correo@ejemplo.com"
                            autoComplete="username"
                            required
                        />
                    </div>
                    <label className="auth-label" htmlFor="loginPassword">
                        Contraseña
                    </label>
                    <div className="auth-input-wrap">
                        <span className="field-icon" aria-hidden="true">
                            ▣
                        </span>
                        <input
                            id="loginPassword"
                            type="password"
                            placeholder="Ingresa tu contraseña"
                            autoComplete="current-password"
                            required
                        />
                        <button
                            type="button"
                            className="password-eye"
                            data-password="loginPassword"
                            aria-label="Mostrar contraseña"
                        >
                            ◉
                        </button>
                    </div>
                    <div className="auth-helpers">
                        <label className="remember-label">
                            <input id="rememberMe" type="checkbox" />
                            <span className="fake-check" />
                            Mantener sesión activa
                        </label>
                        <button type="button" className="text-action" id="forgotPassword">
                            ¿Olvidaste tu contraseña?
                        </button>
                    </div>
                    <p id="loginError" className="auth-feedback" aria-live="polite" />
                    <button type="submit" className="auth-submit">
                        INICIAR SESIÓN <span aria-hidden="true">→</span>
                    </button>
                </form>

                <p className="auth-switch">
                    ¿No tienes una cuenta?{' '}
                    <button type="button" id="showRegister" className="text-action text-action-strong">
                        Crear cuenta de cliente
                    </button>
                </p>

            </section>

            {/* Panel de Registro */}
            <section id="registerPanel" className="auth-panel is-hidden">
                <div className="auth-brand auth-brand-compact">
                    <img
                        className="auth-logo-image"
                        src="/assets/desego-logo.png"
                        alt="Logo de DESEGO CEVICHERÍA"
                    />
                    <div className="auth-brand-name">DESEGO</div>
                    <div className="auth-brand-subtitle">CREAR CUENTA</div>
                </div>
                <div className="auth-intro">
                    <span className="auth-kicker">CUENTA DE CLIENTE</span>
                    <h1>Crea tu cuenta DESEGO</h1>
                    <p>Regístrate para consultar tus pedidos y reservas.</p>
                </div>
                <form id="registerForm" className="auth-form">
                    <label className="auth-label" htmlFor="registerName">
                        Nombre completo
                    </label>
                    <div className="auth-input-wrap">
                        <span className="field-icon" aria-hidden="true">
                            ♙
                        </span>
                        <input
                            id="registerName"
                            type="text"
                            placeholder="Juan Desego"
                            autoComplete="name"
                            required
                            minLength={3}
                        />
                    </div>
                    <label className="auth-label" htmlFor="registerEmail">
                        Correo electrónico
                    </label>
                    <div className="auth-input-wrap">
                        <span className="field-icon" aria-hidden="true">
                            ✉
                        </span>
                        <input
                            id="registerEmail"
                            type="email"
                            placeholder="correo@desego.com"
                            autoComplete="email"
                            required
                        />
                    </div>
                    <label className="auth-label" htmlFor="registerPassword">
                        Contraseña
                    </label>
                    <div className="auth-input-wrap">
                        <span className="field-icon" aria-hidden="true">
                            ▣
                        </span>
                        <input
                            id="registerPassword"
                            type="password"
                            placeholder="Mínimo 10 caracteres"
                            autoComplete="new-password"
                            required
                            minLength={10}
                        />
                        <button
                            type="button"
                            className="password-eye"
                            data-password="registerPassword"
                            aria-label="Mostrar contraseña"
                        >
                            ◉
                        </button>
                    </div>
                    <label className="auth-label" htmlFor="confirmPassword">
                        Confirmar contraseña
                    </label>
                    <div className="auth-input-wrap">
                        <span className="field-icon" aria-hidden="true">
                            ▣
                        </span>
                        <input
                            id="confirmPassword"
                            type="password"
                            placeholder="Repite tu contraseña"
                            autoComplete="new-password"
                            required
                            minLength={10}
                        />
                        <button
                            type="button"
                            className="password-eye"
                            data-password="confirmPassword"
                            aria-label="Mostrar contraseña"
                        >
                            ◉
                        </button>
                    </div>
                    <p id="registerError" className="auth-feedback" aria-live="polite" />
                    <button type="submit" className="auth-submit">
                        CREAR CUENTA <span aria-hidden="true">→</span>
                    </button>
                </form>
                <p className="auth-switch">
                    ¿Ya tienes una cuenta?{' '}
                    <button type="button" id="showLogin" className="text-action text-action-strong">
                        Inicia sesión
                    </button>
                </p>
            </section>

            {/* Panel de Recuperación de Contraseña */}
            <section id="forgotPanel" className="auth-panel is-hidden" aria-labelledby="forgotTitle">
                <div className="auth-brand auth-brand-compact">
                    <img
                        className="auth-logo-image"
                        src="/assets/desego-logo.png"
                        alt="Logo de DESEGO CEVICHERÍA"
                    />
                    <div className="auth-brand-name">DESEGO</div>
                    <div className="auth-brand-subtitle">RECUPERAR ACCESO</div>
                </div>
                <div className="auth-intro">
                    <span className="auth-kicker">ASISTENCIA DE CUENTA</span>
                    <h1 id="forgotTitle">¿Olvidaste tu contraseña?</h1>
                    <p>
                        Escribe el correo asociado a tu cuenta y te enviaremos un enlace seguro para crear una
                        contraseña nueva.
                    </p>
                </div>
                <form id="forgotPasswordForm" className="auth-form" noValidate>
                    <label className="auth-label" htmlFor="forgotEmail">
                        Correo electrónico
                    </label>
                    <div className="auth-input-wrap">
                        <span className="field-icon" aria-hidden="true">
                            ✉
                        </span>
                        <input
                            id="forgotEmail"
                            type="email"
                            placeholder="correo@desego.com"
                            autoComplete="email"
                            required
                            maxLength={190}
                        />
                    </div>
                    <p id="forgotFeedback" className="auth-feedback" aria-live="polite" />
                    <p id="forgotDebugWrap" className="auth-feedback is-hidden">
                        Prueba local:{' '}
                        <a id="forgotDebugLink" href="#" target="_self">
                            abrir enlace de recuperación
                        </a>
                    </p>
                    <button type="submit" className="auth-submit">
                        ENVIAR ENLACE <span aria-hidden="true">→</span>
                    </button>
                </form>
                <p className="auth-switch">
                    <button type="button" id="forgotBackToLogin" className="text-action text-action-strong">
                        ← Volver a iniciar sesión
                    </button>
                </p>
            </section>

            {/* Panel de Nueva Contraseña */}
            <section id="resetPanel" className="auth-panel is-hidden" aria-labelledby="resetTitle">
                <div className="auth-brand auth-brand-compact">
                    <img
                        className="auth-logo-image"
                        src="/assets/desego-logo.png"
                        alt="Logo de DESEGO CEVICHERÍA"
                    />
                    <div className="auth-brand-name">DESEGO</div>
                    <div className="auth-brand-subtitle">NUEVA CONTRASEÑA</div>
                </div>
                <div className="auth-intro">
                    <span className="auth-kicker">RECUPERACIÓN SEGURA</span>
                    <h1 id="resetTitle">Crea una contraseña nueva</h1>
                    <p>
                        Usa al menos 12 caracteres. El enlace solo puede utilizarse una vez y vence después de 30
                        minutos.
                    </p>
                </div>
                <form id="resetPasswordForm" className="auth-form" noValidate>
                    <input type="hidden" id="resetToken" defaultValue="" />
                    <label className="auth-label" htmlFor="newPassword">
                        Nueva contraseña
                    </label>
                    <div className="auth-input-wrap">
                        <span className="field-icon" aria-hidden="true">
                            ▣
                        </span>
                        <input
                            id="newPassword"
                            type="password"
                            autoComplete="new-password"
                            placeholder="Mínimo 12 caracteres"
                            required
                            minLength={12}
                            maxLength={4096}
                        />
                        <button
                            type="button"
                            className="password-eye"
                            data-password="newPassword"
                            aria-label="Mostrar contraseña"
                        >
                            ◉
                        </button>
                    </div>
                    <label className="auth-label" htmlFor="confirmNewPassword">
                        Confirmar contraseña
                    </label>
                    <div className="auth-input-wrap">
                        <span className="field-icon" aria-hidden="true">
                            ▣
                        </span>
                        <input
                            id="confirmNewPassword"
                            type="password"
                            autoComplete="new-password"
                            placeholder="Repite la contraseña"
                            required
                            minLength={12}
                            maxLength={4096}
                        />
                        <button
                            type="button"
                            className="password-eye"
                            data-password="confirmNewPassword"
                            aria-label="Mostrar contraseña"
                        >
                            ◉
                        </button>
                    </div>
                    <p id="resetFeedback" className="auth-feedback" aria-live="polite" />
                    <button type="submit" className="auth-submit">
                        GUARDAR CONTRASEÑA <span aria-hidden="true">→</span>
                    </button>
                </form>
                <p className="auth-switch">
                    <button type="button" id="resetBackToLogin" className="text-action text-action-strong">
                        ← Volver a iniciar sesión
                    </button>
                </p>
            </section>
        </main>
    );
}

