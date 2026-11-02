import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ErrorList } from '../../Components/Restaurant/ShopParts';
import '../../../css/restaurant.css';
import '../../../css/restaurant-account.css';
import '../../../css/restaurant-mobile.css';

export default function Auth({
    register = false,
    staff = false,
    forgot = false,
    reset = false,
    token = '',
}) {
    const { flash } = usePage().props;
    const [passwordVisible, setPasswordVisible] = React.useState(false);
    const isLogin = !register && !forgot && !reset;
    const heading = forgot
        ? 'Recupera tu cuenta.'
        : reset
          ? 'Elige una nueva contraseña.'
          : register
            ? 'Un gusto conocerte.'
            : staff
              ? 'Todo bajo control.'
              : 'Qué bueno verte.';
    const description = forgot
        ? 'Te enviaremos un enlace para restablecer tu contraseña.'
        : reset
          ? 'Usa al menos 12 caracteres y confirma tu nueva contraseña.'
          : register
            ? 'Crea tu cuenta para consultar pedidos, guardar tus direcciones y acumular puntos en cada visita.'
            : staff
              ? 'Ingresa al panel de control de DESEGO.'
              : 'Entra a tu cuenta y sigue disfrutando de tus favoritos.';
    const submitLabel = forgot
        ? 'Enviar enlace de recuperación'
        : reset
          ? 'Guardar nueva contraseña'
          : register
            ? 'Crear mi cuenta'
            : staff
              ? 'Ingresar al panel →'
              : 'Ingresar a mi cuenta →';
    const action = forgot
        ? '/recuperar-contrasena'
        : reset
          ? '/restablecer-contrasena'
          : register
            ? '/registro'
            : staff
              ? '/admin'
              : '/ingresar';
    const form = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        remember: false,
        token,
    });

    return (
        <main className="dg-auth">
            <Head title={heading} />
            <section className="dg-auth-form">
                <Link href="/" className="dg-text-button">
                    ← Volver a la carta
                </Link>
                <div>
                    <div className="dg-auth-logo" aria-hidden="true">
                        <img src="/assets/desego-logo.png" alt="" />
                    </div>
                    <span className="dg-eyebrow">
                        {staff ? 'DESEGO · EQUIPO' : 'BIENVENIDO A TU MESA'}
                    </span>
                    <h2>{heading}</h2>
                    <p>{description}</p>
                    {flash?.success && <p role="status" className="dg-confirmation" style={{ marginBottom: '16px' }}>{flash.success}</p>}
                    {flash?.error && (
                        <div
                            className="dg-error-banner"
                            style={{
                                background: '#fdf2f2',
                                border: '1px solid #f8d7da',
                                color: '#842029',
                                padding: '10px 14px',
                                borderRadius: '4px',
                                fontSize: '12px',
                                marginBottom: '16px',
                            }}
                        >
                            {flash.error}
                        </div>
                    )}

                    {!staff && !forgot && !reset && (
                        <div className="dg-google-auth-wrap">
                            <a href="/auth/google/redirect" className="dg-google-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.15Z" />
                                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.13C3.28 21.36 7.36 24 12 24Z" />
                                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.6H1.25C.45 8.22 0 10.05 0 12s.45 3.78 1.25 5.4l4.03-3.13Z" />
                                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.36 0 3.28 2.64 1.25 6.6l4.03 3.13c.95-2.83 3.6-4.98 6.72-4.98Z" />
                                </svg>
                                <span>{register ? 'Crear cuenta con Google' : 'Continuar con Google'}</span>
                            </a>
                            <div className="dg-auth-divider">
                                <span>o con correo electrónico</span>
                            </div>
                        </div>
                    )}

                    <form
                        className="dg-form"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(action, {
                                onFinish: () =>
                                    form.reset('password', 'password_confirmation'),
                            });
                        }}
                    >
                        <ErrorList errors={form.errors} />
                        {register && (
                            <>
                                <label>
                                    Nombre completo
                                    <input
                                        required
                                        autoComplete="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                </label>
                                <label>
                                    Celular
                                    <input
                                        type="tel"
                                        autoComplete="tel"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData('phone', e.target.value)
                                        }
                                    />
                                </label>
                            </>
                        )}
                        {!reset && (
                            <label>
                                Correo electrónico
                                <input
                                    type="email"
                                    required
                                    autoComplete="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                />
                            </label>
                        )}
                        {!forgot && (
                            <label>
                                Contraseña
                                <span className="dg-auth-password">
                                    <input
                                        type={passwordVisible ? 'text' : 'password'}
                                        required
                                        minLength={reset ? 12 : register ? 10 : 1}
                                        autoComplete={
                                            register || reset ? 'new-password' : 'current-password'
                                        }
                                        value={form.data.password}
                                        onChange={(e) =>
                                            form.setData('password', e.target.value)
                                        }
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setPasswordVisible((visible) => !visible)}
                                        aria-label={passwordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                    >
                                        {passwordVisible ? (
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
                                                <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                                                <path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c5.2 0 9 4.5 10 7a11.8 11.8 0 0 1-3.1 4.4M6.2 6.2C3.9 7.7 2.5 10 2 12c.5 1.3 1.7 3.2 3.7 4.7A10.4 10.4 0 0 0 12 19c.8 0 1.5-.1 2.2-.3" />
                                            </svg>
                                        ) : (
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
                                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        )}
                                    </button>
                                </span>
                            </label>
                        )}
                        {register || reset ? (
                            <label>
                                Confirma tu contraseña
                                <input
                                    type="password"
                                    autoComplete="new-password"
                                    required
                                    value={form.data.password_confirmation}
                                    onChange={(e) =>
                                        form.setData(
                                            'password_confirmation',
                                            e.target.value
                                        )
                                    }
                                />
                            </label>
                        ) : (
                            isLogin && !staff && (
                                <label className="dg-check">
                                    <input
                                        type="checkbox"
                                        checked={form.data.remember}
                                        onChange={(e) =>
                                            form.setData('remember', e.target.checked)
                                        }
                                    />
                                    <span>Recordar mi sesión</span>
                                </label>
                            )
                        )}
                        <button className="dg-button" disabled={form.processing}>
                            {form.processing ? 'Un momento...' : submitLabel}
                        </button>
                    </form>

                    {isLogin && !staff && (
                        <p className="dg-auth-switch">
                            <Link href="/recuperar-contrasena">Olvidé mi contraseña</Link>
                        </p>
                    )}
                    {(forgot || reset) && (
                        <p className="dg-auth-switch">
                            <Link href="/ingresar">Volver a iniciar sesión</Link>
                            {reset && <> · <Link href="/recuperar-contrasena">Solicitar otro enlace</Link></>}
                        </p>
                    )}

                    {!staff && !forgot && !reset && (
                        <p className="dg-auth-switch">
                            {register
                                ? '¿Ya tienes cuenta?'
                                : '¿Primera vez por aquí?'}{' '}
                            <Link href={register ? '/ingresar' : '/registro'}>
                                {register ? 'Ingresa aquí' : 'Crea tu cuenta'}
                            </Link>
                        </p>
                    )}
                </div>
                <small>DESEGO · Cevichería & cocina marina</small>
            </section>
        </main>
    );
}
