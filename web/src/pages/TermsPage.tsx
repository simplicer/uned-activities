/**
 * Terms of Service page.
 */

import { BookOpen } from 'lucide-react';

export function TermsPage() {
  return (
    <div className="max-w-4xl mx-auto">
      <div className="mb-8">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-primary rounded-lg flex items-center justify-center shadow-lg">
            <BookOpen className="w-7 h-7 text-white" />
          </div>
          <div>
            <h1 className="text-3xl font-bold text-foreground">Términos de Uso</h1>
            <p className="text-muted-foreground">Última actualización: Febrero 2026</p>
          </div>
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-border p-8 space-y-8 prose prose-slate max-w-none">
        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">1. Aceptación de los Términos</h2>
          <p className="text-muted-foreground">
            Al acceder y utilizar este servicio de búsqueda de actividades de la UNED, aceptas estos términos de uso.
            Si no estás de acuerdo con estos términos, por favor no utilices este servicio.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">2. Descripción del Servicio</h2>
          <p className="text-muted-foreground">
            Este servicio permite buscar y consultar actividades de extensión universitaria de la Universidad Nacional de Educación a Distancia (UNED).
            La información se obtiene de fuentes públicas y se ofrece tal cual, sin garantías de exactitud o actualización.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">3. Obligaciones del Usuario</h2>
          <ul className="list-disc list-inside text-muted-foreground space-y-2">
            <li>Utilizar el servicio de acuerdo con la legislación vigente</li>
            <li>No realizar actividades que puedan dañar el funcionamiento del servicio</li>
            <li>No reproducir o redistribuir la información sin autorización</li>
            <li>Proporcionar información veraz en los registros de usuario</li>
          </ul>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">4. Propiedad Intelectual</h2>
          <p className="text-muted-foreground">
            Este software es propiedad de <a href="https://simplicer.com" className="text-primary hover:underline">Simplicer SL</a> y se distribuye bajo la licencia <a href="https://opensource.org/licenses/MIT" className="text-primary hover:underline">MIT</a>.
            Los contenidos de la UNED pertenecen a dicha institución.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">5. Limitación de Responsabilidad</h2>
          <p className="text-muted-foreground">
            El servicio se ofrece "tal cual" sin garantías de ningún tipo. Simplicer SL no se hace responsable de:
          </p>
          <ul className="list-disc list-inside text-muted-foreground space-y-2">
            <li>La exactitud o actualización de la información mostrada</li>
            <li>Daños directos o indirectos derivados del uso del servicio</li>
            <li>La disponibilidad continúa del servicio</li>
          </ul>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">6. Protección de Datos</h2>
          <p className="text-muted-foreground">
            Los datos personales se tratan conforme a la Política de Privacidad. Al usar el servicio, consientes el tratamiento de tus datos según lo descrito en dicha política.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">7. Modificaciones</h2>
          <p className="text-muted-foreground">
            Simplicer SL se reserva el derecho de modificar estos términos en cualquier momento. Las modificaciones entrarán en vigor desde su publicación en esta página.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">8. Contacto</h2>
          <p className="text-muted-foreground">
            Para cualquier cuestión sobre estos términos, puedes contactar con Simplicer SL a través de su web <a href="https://simplicer.com" className="text-primary hover:underline">simplicer.com</a>.
          </p>
        </section>
      </div>
    </div>
  );
}
