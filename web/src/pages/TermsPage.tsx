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

      <div className="bg-card rounded-xl shadow-sm border border-border p-8 space-y-8 prose prose-slate dark:prose-invert max-w-none">
        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">1. Aceptación</h2>
          <p className="text-muted-foreground">
            Al acceder y utilizar este servicio aceptas estos Términos de Uso. Si no estás de acuerdo, no utilices el servicio.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">2. Descripción del servicio</h2>
          <p className="text-muted-foreground">
            Este sitio es un buscador independiente de actividades de extensión de la UNED. La información se obtiene de fuentes
            públicas y se ofrece tal cual, sin garantías de exactitud o actualización. No somos la UNED ni estamos afiliados a ella.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">3. Uso permitido</h2>
          <ul className="list-disc list-inside text-muted-foreground space-y-2">
            <li>Usar el servicio conforme a la legislación vigente y a estos términos.</li>
            <li>No intentar interrumpir, degradar o comprometer la seguridad del servicio.</li>
            <li>No realizar scraping masivo ni automatizado desde este sitio.</li>
            <li>No suplantar identidad ni proporcionar datos falsos al registrarse.</li>
          </ul>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">4. Propiedad intelectual</h2>
          <p className="text-muted-foreground">
            El software se distribuye bajo licencia MIT. Los contenidos y marcas de la UNED pertenecen a dicha institución.
            Este sitio no reclama derechos sobre los contenidos de la UNED.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">5. Limitación de responsabilidad</h2>
          <ul className="list-disc list-inside text-muted-foreground space-y-2">
            <li>La información puede contener errores o estar desactualizada.</li>
            <li>No garantizamos la disponibilidad continua del servicio.</li>
            <li>No asumimos responsabilidad por daños derivados del uso del servicio.</li>
          </ul>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">6. Protección de datos</h2>
          <p className="text-muted-foreground">
            El tratamiento de datos personales se regula en la Política de Privacidad. Al utilizar el servicio aceptas dicho tratamiento.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">7. Modificaciones</h2>
          <p className="text-muted-foreground">
            Estos términos pueden modificarse en cualquier momento. Las modificaciones entran en vigor desde su publicación en esta página.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">8. Titular y contacto</h2>
          <p className="text-muted-foreground">
            Titular del servicio: <strong>Antonio Villamarin</strong>. Para contacto, consulta los canales públicos del
            repositorio en Codeberg.
          </p>
        </section>
      </div>
    </div>
  );
}
