import { promises as fs } from "fs";

export class AssetsScssFile {
    constructor() {
        this.destination = "./resources/scss/_assets.scss";
    }

    async createSCSSFile(variables) {
        this.mappedVariables = variables;
        try {
            await fs.writeFile(this.destination, this.getScssContent());
            console.log(this.destination + " file successfully created!");
        } catch (err) {
            console.error("Une erreur est survenue :", err);
        }
    }

    getScssContent() {
        let content = "/**** Auto-generated file, DO NOT EDIT ****/";

        this.mappedVariables.forEach((value, variable) => {
            const parsedValue = Boolean(value) ? `'${value}'` : value;
            content += ` ${variable}:${parsedValue};`;
        });

        return content;
    }
}
