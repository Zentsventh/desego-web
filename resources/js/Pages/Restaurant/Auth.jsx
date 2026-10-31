import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Brand, ErrorList } from '../../Components/Restaurant/ShopParts';
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
    const isLogin = !register && !forgot && !reset;
    const heading = forgot ? 'Recupera tu cuenta.' : reset ? 'Elige una nueva contraseña.' : register ? 'Un gusto conocerte.' : staff ? 'Todo bajo control.' : 'Qué bueno verte.';
    const description = forgot
        ? 'Te enviaremos un enlace para restablecer tu contraseña.'
        : reset
          ? 'Usa al menos 12 caracteres y confirma tu nueva contraseña.'
          : register
            ? 'Crea tu cuenta para consultar pedidos, guardar tus direcciones y reservar tu próximo encuentro.'
            : staff
              ? 'Ingresa al panel de control de DESEGO.'
              : 'Entra a tu cuenta y sigue disfrutando de tus favoritos.';
    const submitLabel = forgot
        ? 'Enviar enlace de recuperación'
        : reset
          ? 'Guardar nueva contraseña'
          : register
            ? 'Crear mi cuenta'
            : staff ? 'Ingresar al panel →' : 'Ingresar a mi cuenta →';
    const action = forgot ? '/recuperar-contrasena' : reset ? '/restablecer-contrasena' : register ? '/registro' : '/ingresar';
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
            <aside className="dg-auth-photo">
                <img
                    src="/images/menu/food_ceviche.jpg"
                    alt="Ceviche de nuestra cocina"
                />
                <div />
                <Brand light />
                <section>
                    <span className="dg-eyebrow dg-eyebrow-light">
                        SABORES QUE NOS ENCUENTRAN
                    </span>
                    <h1>
                        Siempre hay
                        <br />
                        un buen motivo
                        <br />
                        para <em>volver.</em>
                    </h1>
                    <p>Tu próxima experiencia empieza aquí.</p>
                </section>
            </aside>
            <section className="dg-auth-form">
                <Link href="/" className="dg-text-button">
                    ← Volver a la carta
                </Link>
                <div>
                    <span className="dg-eyebrow">
                        {staff ? 'DESEGO · EQUIPO' : 'BIENVENIDO A TU MESA'}
                    </span>
                    <h2>{heading}</h2>
                    <p>{description}</p>
                    {flash?.success && <p role="status">{flash.success}</p>}
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
                        {!reset && <label>
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
                        </label>}
                        {!forgot && <label>
                            Contraseña
                            <input
                                type="password"
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
                        </label>}
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
                        ) : isLogin && (
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
                        )}
                        <button className="dg-button" disabled={form.processing}>
                            {form.processing ? 'Un momento...' : submitLabel}
                        </button>
                    </form>

                    {isLogin && (
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
                    <span className="dg-auth-security">
                        ✓ Tus datos se gestionan en DESEGO.
                    </span>
                </div>
                <small>DESEGO · Cevichería & cocina marina</small>
            </section>
        </main>
    );
}
