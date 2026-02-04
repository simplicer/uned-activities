/**
 * Privacy Policy page.
 */

import { Shield } from 'lucide-react';

export function PrivacyPage() {
  return (
    <div className="max-w-4xl mx-auto">
      <div className="mb-8">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-primary rounded-lg flex items-center justify-center shadow-lg">
            <Shield className="w-7 h-7 text-white" />
          </div>
          <div>
            <h1 className="text-3xl font-bold text-foreground">Política de Privacidad</h1>
            <p className="text-muted-foreground">Última actualización: Febrero 2026</p>
          </div>
        </div>
      </div>

      <div className="bg-card rounded-xl shadow-sm border border-border p-8 space-y-8 prose prose-slate dark:prose-invert max-w-none">
        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">1. Responsable</h2>
          <p className="text-muted-foreground">
            Responsable del tratamiento: <strong>SIMPLICER, S.L.</strong> (titular de lexemas.com), con domicilio en
            Av. de la Libertad, 37, 04639, Turre, Almería, España.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">2. Datos que tratamos</h2>

          <h3 className="text-xl font-semibold text-foreground mt-6 mb-3">2.1 Datos de navegación</h3>
          <p className="text-muted-foreground">
            Datos técnicos como IP, navegador, idioma y páginas visitadas, necesarios para seguridad y funcionamiento.
          </p>

          <h3 className="text-xl font-semibold text-foreground mt-6 mb-3">2.2 Datos de cuenta</h3>
          <p className="text-muted-foreground">
            Si te registras, tratamos tu correo electrónico y preferencias asociadas al perfil.
          </p>

          <h3 className="text-xl font-semibold text-foreground mt-6 mb-3">2.3 Cookies</h3>
          <p className="text-muted-foreground">
            Solo usamos cookies técnicas necesarias para el funcionamiento básico (por ejemplo, sesión y preferencias).
            No utilizamos cookies de analítica ni publicidad.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">3. Finalidad del tratamiento</h2>
          <ul className="list-disc list-inside text-muted-foreground space-y-2">
            <li>Prestar el servicio de búsqueda de actividades</li>
            <li>Gestionar el acceso y preferencias del usuario</li>
            <li>Garantizar la seguridad y correcto funcionamiento del sitio</li>
          </ul>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">4. Base legal</h2>
          <ul className="list-disc list-inside text-muted-foreground space-y-2">
            <li>Ejecución del servicio solicitado</li>
            <li>Interés legítimo en la seguridad y mantenimiento</li>
            <li>Consentimiento cuando sea necesario</li>
          </ul>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">5. Destinatarios</h2>
          <p className="text-muted-foreground">
            No cedemos datos a terceros, salvo obligación legal o proveedores necesarios para operar el servicio, con garantías adecuadas.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">6. Derechos del Usuario</h2>
          <ul className="list-disc list-inside text-muted-foreground space-y-2">
            <li>Acceder a tus datos personales</li>
            <li>Solicitar la rectificación de datos inexactos</li>
            <li>Solicitar la supresión de tus datos</li>
            <li>Oponerte al tratamiento</li>
            <li>Solicitar la portabilidad de tus datos</li>
            <li>Retirar el consentimiento en cualquier momento</li>
          </ul>
          <p className="text-muted-foreground mt-4">
            Para ejercer estos derechos, utiliza el <a className="text-primary hover:underline" href="/contact">formulario de contacto</a>.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">7. Conservación de Datos</h2>
          <p className="text-muted-foreground">
            Los datos se conservarán mientras exista una cuenta activa o durante el tiempo necesario para cumplir con obligaciones legales.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">8. Seguridad</h2>
          <p className="text-muted-foreground">
            Hemos adoptado las medidas técnicas y organizativas necesarias para garantizar la seguridad de tus datos personales y protegerlos contra accesos no autorizados.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">9. Origen de los datos públicos</h2>
          <p className="text-muted-foreground">
            La información sobre actividades se obtiene de la web pública de la UNED (<a className="text-primary hover:underline" href="https://extension.uned.es" target="_blank" rel="noopener noreferrer">extension.uned.es</a>).
            La información puede no ser exacta o estar desactualizada. Se ofrece enlace a la fuente original para su verificación.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">10. Cumplimiento legal</h2>
          <p className="text-muted-foreground">
            Esta política se adapta al RGPD (UE 2016/679), la LOPDGDD y la LSSI-CE. No se realizan actividades de comercio electrónico.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">11. Cambios en esta Política</h2>
          <p className="text-muted-foreground">
            Los cambios se publicarán en esta página y, si corresponde, se informará a los usuarios registrados.
          </p>
        </section>
      </div>
    </div>
  );
}
