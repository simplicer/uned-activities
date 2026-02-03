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
            Responsable del tratamiento: <strong>Antonio Villamarin</strong>. Contacto a través de los canales públicos del
            repositorio en Codeberg.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">2. Datos que recopilamos</h2>

          <h3 className="text-xl font-semibold text-foreground mt-6 mb-3">2.1 Datos de navegación</h3>
          <p className="text-muted-foreground">
            Recopilamos datos técnicos como la dirección IP, tipo de navegador, idioma, y páginas visitadas para mejorar el servicio.
          </p>

          <h3 className="text-xl font-semibold text-foreground mt-6 mb-3">2.2 Datos de cuenta</h3>
          <p className="text-muted-foreground">
            Si te registras, tratamos tu correo electrónico y tus preferencias de notificación.
          </p>

          <h3 className="text-xl font-semibold text-foreground mt-6 mb-3">2.3 Cookies</h3>
          <p className="text-muted-foreground">
            Utilizamos cookies técnicas para el funcionamiento del sitio y cookies de preferencias (idioma, filtros).
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">3. Finalidad del tratamiento</h2>
          <ul className="list-disc list-inside text-muted-foreground space-y-2">
            <li>Prestar el servicio de búsqueda de actividades</li>
            <li>Gestionar las preferencias del usuario</li>
            <li>Enviar notificaciones sobre nuevas actividades (si se solicita)</li>
            <li>Mejorar la funcionalidad del sitio</li>
          </ul>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">4. Base legal</h2>
          <p className="text-muted-foreground">
            El tratamiento de tus datos se basa en:
          </p>
          <ul className="list-disc list-inside text-muted-foreground space-y-2">
            <li>Consentimiento del titular al registrarse</li>
            <li>Ejecución de un contrato de servicios</li>
            <li>Interés legítimo para mejorar el servicio</li>
          </ul>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">5. Destinatarios</h2>
          <p className="text-muted-foreground">
            No cedemos datos a terceros, salvo obligación legal. Los datos se alojan en infraestructura bajo medidas de seguridad adecuadas.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">6. Derechos del Usuario</h2>
          <p className="text-muted-foreground">
            Tienes derecho a:
          </p>
          <ul className="list-disc list-inside text-muted-foreground space-y-2">
            <li>Acceder a tus datos personales</li>
            <li>Solicitar la rectificación de datos inexactos</li>
            <li>Solicitar la supresión de tus datos</li>
            <li>Oponerte al tratamiento</li>
            <li>Solicitar la portabilidad de tus datos</li>
            <li>Retirar el consentimiento en cualquier momento</li>
          </ul>
          <p className="text-muted-foreground mt-4">
            Para ejercer estos derechos, contacta a través de los canales públicos del repositorio.
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
          <h2 className="text-2xl font-bold text-foreground mb-4">9. Cambios en esta Política</h2>
          <p className="text-muted-foreground">
            Los cambios se publicarán en esta página y, si corresponde, se informará a los usuarios registrados.
          </p>
        </section>
      </div>
    </div>
  );
}
